import json
from urllib import request
import mysql.connector
import time
import random
import os
import requests
import json
import boto3
from botocore.exceptions import NoCredentialsError
from dotenv import load_dotenv
from pathlib import Path
from shutil import copyfile
import tempfile
import math

current_dir = Path(__file__).resolve().parent
env_path = current_dir.parent / '.env'
load_dotenv(env_path)

#import vertexai
#from vertexai.preview.vision_models import ImageGenerationModel
#from google.oauth2 import service_account
#from google.api_core.exceptions import GoogleAPIError
#import traceback

import fal_client


# Existing environment variables
API_BASE_URL = os.getenv('API_BASE_URL')
OUTPUT_DIR = os.getenv('OUTPUT_DIR')
MOVE_TO_DIR = os.getenv('MOVE_TO_DIR')
OPENROUTER_API_KEY = os.getenv('OPEN_ROUTER_API_KEY')
YOUR_SITE_URL = os.getenv('OPEN_ROUTER_YOUR_SITE_URL')
YOUR_APP_NAME = os.getenv('OPEN_ROUTER_YOUR_APP_NAME')
MODEL = os.getenv('OPEN_ROUTER_MODEL')

# AWS environment variables
AWS_ACCESS_KEY_ID = os.getenv('AWS_ACCESS_KEY_ID')
AWS_SECRET_ACCESS_KEY = os.getenv('AWS_SECRET_ACCESS_KEY')
AWS_BUCKET = os.getenv('AWS_BUCKET')
AWS_REGION = os.getenv('AWS_DEFAULT_REGION')
AWS_CLOUDFRONT_URL = os.getenv('AWS_CLOUDFRONT_URL')

# Initialize S3 client
s3_client = boto3.client(
    's3',
    aws_access_key_id=AWS_ACCESS_KEY_ID,
    aws_secret_access_key=AWS_SECRET_ACCESS_KEY,
    region_name=AWS_REGION
)

#credentials = service_account.Credentials.from_service_account_file(
# os.getenv('GOOGLE_AUTH_KEY_PATH',"google.json"),
# scopes=["https://www.googleapis.com/auth/cloud-platform"],)

#vertexai.init(project=os.getenv('GOOGLE_PROJECT_ID',""), location="us-central1", credentials=credentials)
#vertexai_model = ImageGenerationModel.from_pretrained("imagen-3.0-generate-002")


def get_aspect_ratio(width, height):
    # Define standard aspect ratios
    standard_ratios = {
        "1:1": 1.0,
        "16:9": 16/9,
        "4:3": 4/3,
        "3:2": 3/2,
        "2:3": 2/3,
        "3:4": 3/4,
        "9:16": 9/16,
        "21:9": 21/9
    }

    # Calculate the actual ratio
    if height == 0:
        return "1:1"  # Avoid division by zero

    actual_ratio = width / height

    # Find the closest standard ratio
    closest_ratio = min(standard_ratios.items(), key=lambda x: abs(x[1] - actual_ratio))

    return closest_ratio[0]

def download_image(url, output_path):
    """Download an image from a URL to a local path"""
    try:
        response = requests.get(url, stream=True)
        response.raise_for_status()

        with open(output_path, 'wb') as f:
            for chunk in response.iter_content(chunk_size=8192):
                f.write(chunk)

        print(f"Successfully downloaded image from {url} to {output_path}")
        return output_path
    except Exception as e:
        print(f"Error downloading image from {url}: {e}")
        return None


def upload_to_s3(local_file, s3_file):
    """Upload a file to S3"""
    try:
        s3_client.upload_file(local_file, AWS_BUCKET, s3_file)
        s3_url = f"{AWS_CLOUDFRONT_URL}/{s3_file}"
        return s3_url
    except NoCredentialsError:
        print("AWS credentials not available")
        return None
    except Exception as e:
        print(f"Error uploading to S3: {e}")
        return None


def update_image_filename(id, file_path, is_s3_url=True):
    """Update record via API with S3 URL"""
    try:
        response = requests.post(f"{API_BASE_URL}/prompts/update-filename", json={
            'id': id,
            'filename': file_path
        })
        if response.status_code == 200:
            print(f"Updated prompts table for id {id} with path {file_path}")
        else:
            print(f"Error updating prompt: {response.json()}")
    except Exception as err:
        print(f"Error updating prompt via API: {err}")

def update_render_status(id, status):
    """Update render status via API"""
    try:
        response = requests.post(f"{API_BASE_URL}/prompts/update-status", json={
            'id': id,
            'status': status
        })
        if response.status_code == 200:
            print(f"Updated render status for prompt {id} to {status}")
        else:
            print(f"Error updating render status: {response.json()}")
    except Exception as err:
        print(f"Error updating render status via API: {err}")

prompt_status_counter = {}

def generate_images_from_api():
    global prompt_status_counter
    #global vertexai_model

    try:
        print("Starting image generation from API (Remote Jobs)...")
        response = requests.get(f"{API_BASE_URL}/prompts/pending")
        if response.status_code != 200:
            print(f"Error fetching prompts: {response.json()}")
            return

        prompts = response.json()['prompts']

        for idx, prompt in enumerate(prompts):

            prompt_id = prompt['id']
            render_status = prompt['render_status']
            generation_type = prompt['generation_type']
            model = prompt['model']

            if generation_type in ["prompt"] and model in ["imagen3", "aura-flow", "ideogram-v2a", "luma-photon", "recraft-20b", "minimax", "minimax-expand"]:
                pass
            else:
                print(f"Skipping prompt {prompt_id} - local model")
                continue

            print(f"Processing prompt {idx + 1} id: {prompt_id} - type: {prompt['generation_type']} - model: {prompt['model']} - status: {render_status} - user id: {prompt['user_id']}")

            try:
                output_filename = f"{generation_type}_{model}_{prompt_id}_{prompt['user_id']}.png"
                output_file = str(Path(OUTPUT_DIR) / output_filename)
                s3_file_path = f"images/{output_filename}"

                Path(OUTPUT_DIR).mkdir(parents=True, exist_ok=True)

                if render_status in (1, 3):

                    skip_continue = False
                    prompt_status_counter[prompt_id] = prompt_status_counter.get(prompt_id, 0) + 1
                    if prompt_status_counter[prompt_id] > 20:
                        # skip_continue = True
                        print(f"Skipping prompt {prompt_id} - seen with status 1 more than 10 times, try render again")
                        update_render_status(prompt_id, 4)
                        del prompt_status_counter[prompt_id]

                    if os.path.exists(output_file):
                        print(f"Found existing image for prompt {prompt_id}")
                        if prompt['upload_to_s3']:
                            s3_url = upload_to_s3(output_file, s3_file_path)
                            if s3_url:
                                update_image_filename(prompt_id, s3_url)
                        else:
                            update_image_filename(prompt_id, output_file, False)

                    if not skip_continue:
                        continue


                workflow = {}
                if generation_type == "prompt":
                    if model == "imagen3":
                        print(f"Sending to Fal/Imagen4: {prompt['generated_prompt']}...")

                        fal_result = fal_client.subscribe(
                            "fal-ai/imagen4/preview/ultra",
                            arguments={
                                "prompt": prompt['generated_prompt']
                            },
                            with_logs=False,
                            # on_queue_update=on_queue_update,
                        )
                        print(fal_result)
                        first_image_url = fal_result["images"][0]["url"]
                        image_response = requests.get(first_image_url)
                        if image_response.status_code == 200:
                            # Save the image to file
                            with open(output_file, 'wb') as f:
                                f.write(image_response.content)
                            print(f"Image saved to {output_file}")
                        else:
                            print(f"Failed to download image: {image_response.status_code}")
                        time.sleep(6)


#                         print(f"Sending to Imagen: {prompt['generated_prompt']}...")
#                         aspect_ratio_value = get_aspect_ratio(prompt['width'], prompt['height'])
#                         print(f"Using aspect ratio: {aspect_ratio_value}")
#                         try:
#                             images = vertexai_model.generate_images(
#                                 prompt=prompt['generated_prompt'],
#                                 number_of_images=1,
#                                 language="en",
#                                 add_watermark=False,
#                                 # seed=100,
#                                 aspect_ratio=aspect_ratio_value,
#                                 safety_filter_level="block_only_high",
#                                 person_generation="allow_adult",
#                             )
#
#                             print(images)
#                             images[0].save(location=output_file, include_generation_parameters=False)
#                         except GoogleAPIError as e:
#                             print(f"Google API Error: {e}")
#                             print(f"Error details: {e.details() if hasattr(e, 'details') else 'No details available'}")
#                             print(f"Error code: {e.code if hasattr(e, 'code') else 'No code available'}")
#                         except Exception as e:
#                             update_render_status(prompt_id, 4)
#                             print(f"Unexpected error: {e}")
#                             traceback.print_exc()
#                             continue
#                         time.sleep(20)

                    elif model == "aura-flow":
                        print(f"Sending to Fal/Aura-Flow: {prompt['generated_prompt']}...")

                        fal_result = fal_client.subscribe(
                            "fal-ai/aura-flow",
                            arguments={
                                "prompt": prompt['generated_prompt']
                            },
                            with_logs=False,
                            # on_queue_update=on_queue_update,
                        )
                        print(fal_result)
                        first_image_url = fal_result["images"][0]["url"]
                        image_response = requests.get(first_image_url)
                        if image_response.status_code == 200:
                            # Save the image to file
                            with open(output_file, 'wb') as f:
                                f.write(image_response.content)
                            print(f"Image saved to {output_file}")
                        else:
                            print(f"Failed to download image: {image_response.status_code}")
                        time.sleep(6)

                    elif model == "ideogram-v2a":
                        print(f"Sending to Fal/ideogram-v2a: {prompt['generated_prompt']}...")

                        fal_result = fal_client.subscribe(
                            "fal-ai/ideogram/v2a",
                            arguments={
                                "prompt": prompt['generated_prompt']
                            },
                            with_logs=False,
                            # on_queue_update=on_queue_update,
                        )
                        print(fal_result)
                        first_image_url = fal_result["images"][0]["url"]
                        image_response = requests.get(first_image_url)
                        if image_response.status_code == 200:
                            # Save the image to file
                            with open(output_file, 'wb') as f:
                                f.write(image_response.content)
                            print(f"Image saved to {output_file}")
                        else:
                            print(f"Failed to download image: {image_response.status_code}")
                        time.sleep(6)

                    elif model == "luma-photon":
                        print(f"Sending to Fal/luma-photon: {prompt['generated_prompt']}...")

                        fal_result = fal_client.subscribe(
                            "fal-ai/luma-photon",
                            arguments={
                                "prompt": prompt['generated_prompt']
                            },
                            with_logs=False,
                            # on_queue_update=on_queue_update,
                        )
                        print(fal_result)
                        first_image_url = fal_result["images"][0]["url"]
                        image_response = requests.get(first_image_url)
                        if image_response.status_code == 200:
                            # Save the image to file
                            with open(output_file, 'wb') as f:
                                f.write(image_response.content)
                            print(f"Image saved to {output_file}")
                        else:
                            print(f"Failed to download image: {image_response.status_code}")
                        time.sleep(6)

                    elif model == "recraft-20b":
                        print(f"Sending to Fal/recraft-20b: {prompt['generated_prompt']}...")

                        fal_result = fal_client.subscribe(
                            "fal-ai/recraft-20b",
                            arguments={
                                "prompt": prompt['generated_prompt']
                            },
                            with_logs=False,
                            # on_queue_update=on_queue_update,
                        )
                        print(fal_result)
                        first_image_url = fal_result["images"][0]["url"]
                        image_response = requests.get(first_image_url)
                        if image_response.status_code == 200:
                            # Save the image to file
                            with open(output_file, 'wb') as f:
                                f.write(image_response.content)
                            print(f"Image saved to {output_file}")
                        else:
                            print(f"Failed to download image: {image_response.status_code}")
                        time.sleep(6)

                    elif model == "minimax":
                        print(f"Sending to Minimax: {prompt['generated_prompt']}...")

                        payload = json.dumps({
                          "model": "image-01",
                          "prompt": prompt['generated_prompt'],
                          "aspect_ratio": get_aspect_ratio(prompt['width'],prompt['height']),
                          "response_format": "url",
                          "n": 1,
                          "prompt_optimizer": False
                        })
                        headers = {
                          'Authorization': f'Bearer {os.getenv("MINIMAX_KEY")}',
                          'Content-Type': 'application/json'
                        }

                        response = requests.request("POST", os.getenv("MINIMAX_KEY_URL"), headers=headers, data=payload)
                        response_json = response.json()

                        print(response_json)
                        first_image_url = response_json["data"]["image_urls"][0]

                        image_response = requests.get(first_image_url)
                        if image_response.status_code == 200:
                            # Save the image to file
                            with open(output_file, 'wb') as f:
                                f.write(image_response.content)
                            print(f"Image saved to {output_file}")
                        else:
                            print(f"Failed to download image: {image_response.status_code}")
                        time.sleep(6)
                    elif model == "minimax-expand":
                        print(f"Sending to Minimax: {prompt['generated_prompt']}...")

                        payload = json.dumps({
                          "model": "image-01",
                          "prompt": prompt['generated_prompt'],
                          "aspect_ratio": get_aspect_ratio(prompt['width'],prompt['height']),
                          "response_format": "url",
                          "n": 1,
                          "prompt_optimizer": True
                        })
                        headers = {
                          'Authorization': f'Bearer {os.getenv("MINIMAX_KEY")}',
                          'Content-Type': 'application/json'
                        }

                        response = requests.request("POST", os.getenv("MINIMAX_KEY_URL"), headers=headers, data=payload)
                        response_json = response.json()

                        print(response_json)
                        first_image_url = response_json["data"]["image_urls"][0]

                        image_response = requests.get(first_image_url)
                        if image_response.status_code == 200:
                            # Save the image to file
                            with open(output_file, 'wb') as f:
                                f.write(image_response.content)
                            print(f"Image saved to {output_file}")
                        else:
                            print(f"Failed to download image: {image_response.status_code}")
                        time.sleep(6)

                if os.path.exists(output_file):
                    print(f"Image exists for prompt {prompt_id}, uploading to S3...")
                    if prompt['upload_to_s3']:
                        s3_url = upload_to_s3(output_file, s3_file_path)
                        if s3_url:
                            update_image_filename(prompt_id, s3_url)
                    else:
                        update_image_filename(prompt_id, output_file, False)
                else:
                    if os.path.exists(output_file):
                        if prompt['upload_to_s3']:
                            s3_url = upload_to_s3(output_file, s3_file_path)
                            if s3_url:
                                update_image_filename(prompt_id, s3_url)
                        else:
                            update_image_filename(prompt_id, output_file, False)
                    else:
                        print(f"Image generation not yet ready for prompt {prompt_id}")
                        update_render_status(prompt_id, 3)

            except Exception as e:
                print(f"Error processing prompt {prompt_id}: {e}")
                update_render_status(prompt_id, 4)

    except Exception as e:
        print(f"Error in generate_images_from_api: {e}")


if __name__ == "__main__":
    while True:
        generate_images_from_api()
        time.sleep(5)
