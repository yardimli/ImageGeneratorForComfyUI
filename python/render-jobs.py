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

current_dir = Path(__file__).resolve().parent
env_path = current_dir.parent / '.env'
load_dotenv(env_path)

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


def queue_prompt(prompt):
    p = {"prompt": prompt}
    data = json.dumps(p).encode('utf-8')
    req = request.Request("http://127.0.0.1:8188/prompt", data=data)
    request.urlopen(req)


def get_workflow_file(generation_type,model):
    """Get the appropriate workflow file based on type"""
    workflow_file = ""
    if generation_type == "prompt":
        if model == "schnell":
            workflow_file = "flux_schnell_for_image_gen.json"
        elif model == "dev":
            workflow_file = "flux_dev_for_image_gen.json"
    elif generation_type == "outpaint":
        workflow_file = "flux_outpaint_for_image_gen.json"
    elif generation_type == "mix":
        workflow_file = "flux_two_image_mix_for_image_gen.json"
    else:
        raise ValueError(f"Unknown generation type: {generation_type}")

    file_path = os.path.join(current_dir, workflow_file)
    with open(file_path, 'r') as file:
        return json.load(file)


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


def generate_images_from_api():
    try:
        print("Starting image generation from API...")
        response = requests.get(f"{API_BASE_URL}/prompts/pending")
        if response.status_code != 200:
            print(f"Error fetching prompts: {response.json()}")
            return

        prompts = response.json()['prompts']

        for idx, prompt in enumerate(prompts):
            print(f"Processing prompt {idx + 1} id: {prompt['id']} {prompt['generation_type']} {prompt['model']}")

            try:
                generation_type = prompt['generation_type']
                model = prompt['model']
                output_filename = f"{generation_type}_{model}_{prompt['id']}_{prompt['user_id']}.png"
                output_file = str(Path(OUTPUT_DIR) / output_filename)
                s3_file_path = f"images/{output_filename}"

                if prompt['render_status'] in (1, 3):
                    if os.path.exists(output_file):
                        print(f"Found existing image for prompt {prompt['id']}")
                        if prompt['upload_to_s3']:
                            s3_url = upload_to_s3(output_file, s3_file_path)
                            if s3_url:
                                update_image_filename(prompt['id'], s3_url)
                        else:
                            update_image_filename(prompt['id'], output_file, False)
                    continue

                workflow = get_workflow_file(generation_type,model)

                if generation_type == "prompt":
                    if model == "schnell":
                        workflow["6"]["inputs"]["text"] = prompt['generated_prompt']
                        workflow["25"]["inputs"]["noise_seed"] = random.randint(1, 2**32)
                        workflow["31"]["inputs"]["file_name_template"] = f"{generation_type}_{model}_{prompt['id']}_{prompt['user_id']}.png"
                        workflow["5"]["inputs"]["width"] = prompt['width']
                        workflow["5"]["inputs"]["height"] = prompt['height']
                    elif model == "dev":
                        workflow["6"]["inputs"]["text"] = prompt['generated_prompt']
                        workflow["25"]["inputs"]["noise_seed"] = random.randint(1, 2**32)
                        workflow["41"]["inputs"]["file_name_template"] = f"{generation_type}_{model}_{prompt['id']}_{prompt['user_id']}.png"
                        workflow["27"]["inputs"]["width"] = prompt['width']
                        workflow["27"]["inputs"]["height"] = prompt['height']
                        workflow["30"]["inputs"]["width"] = prompt['width']
                        workflow["30"]["inputs"]["height"] = prompt['height']
                elif generation_type == "outpaint":
                    # load source image from absolute path
                    workflow["17"]["inputs"]["image"] = prompt['source_image']

                    workflow["23"]["inputs"]["text"] = prompt['generated_prompt']
                    workflow["3"]["inputs"]["seed"] = random.randint(1, 2**32)
                    workflow["41"]["inputs"]["file_name_template"] = f"{generation_type}_{model}_{prompt['id']}_{prompt['user_id']}.png"

                    # postprocessing resize width and height (proportional)
                    workflow["46"]["inputs"]["width"] = prompt['width']
                    workflow["46"]["inputs"]["height"] = prompt['height']

                    # padding
                    workflow["44"]["inputs"]["left"] = prompt['left_padding']
                    workflow["44"]["inputs"]["right"] = prompt['right_padding']
                    workflow["44"]["inputs"]["top"] = prompt['top_padding']
                    workflow["44"]["inputs"]["bottom"] = prompt['bottom_padding']
                    workflow["44"]["inputs"]["feathering"] = prompt['feathering']
                elif generation_type == "mix":
                    temp_dir = tempfile.mkdtemp()

                    # Download images
                    image1_path = os.path.join(temp_dir, "image1.png")
                    image2_path = os.path.join(temp_dir, "image2.png")

                    # Download image 1
                    if not download_image(prompt['input_image_1'], image1_path):
                        raise Exception(f"Failed to download image 1 from {prompt['input_image_1']}")

                    # Download image 2
                    if not download_image(prompt['input_image_2'], image2_path):
                        raise Exception(f"Failed to download image 2 from {prompt['input_image_2']}")

                    # load source image from absolute path
                    workflow["40"]["inputs"]["image"] = image1_path
                    workflow["56"]["inputs"]["image"] = image2_path

                    workflow["54"]["inputs"]["downsampling_factor"] = prompt.get('input_image_1_strength', 1)
                    workflow["55"]["inputs"]["downsampling_factor"] = prompt.get('input_image_2_strength', 1)

                    workflow["6"]["inputs"]["text"] = prompt['generated_prompt']
                    workflow["25"]["inputs"]["noise_seed"] = random.randint(1, 2**32)
                    workflow["57"]["inputs"]["file_name_template"] = f"{generation_type}_{model}_{prompt['id']}_{prompt['user_id']}.png"

                    # postprocessing resize width and height (proportional)
                    workflow["27"]["inputs"]["width"] = prompt['width']
                    workflow["27"]["inputs"]["height"] = prompt['height']

                    workflow["30"]["inputs"]["width"] = prompt['width']
                    workflow["30"]["inputs"]["height"] = prompt['height']

                    print("Debugging mix prompt:")
                    print (f"Using images with strengths: {prompt.get('input_image_1', 1)} and {prompt.get('input_image_2', 1)} :: {prompt.get('input_image_1_strength', 1)} and {prompt.get('input_image_2_strength', 1)}")
                    print (f"Prompt and width and height: {prompt['generated_prompt']} :: {prompt['width']} and {prompt['height']}")


                if os.path.exists(output_file):
                    print(f"Image exists for prompt {prompt['id']}, uploading to S3...")
                    if prompt['upload_to_s3']:
                        s3_url = upload_to_s3(output_file, s3_file_path)
                        if s3_url:
                            update_image_filename(prompt['id'], s3_url)
                    else:
                        update_image_filename(prompt['id'], output_file, False)
                else:
                    print(f"Rendering image for prompt {prompt['id']}")
                    queue_prompt(workflow)
                    update_render_status(prompt['id'], 1)
                    print(f"Queued prompt for: {prompt['generated_prompt']}...")

                    wait_time = 60
                    if generation_type == "prompt":
                        if model == "schnell":
                            wait_time = 15
                        elif model == "dev":
                            wait_time = 25
                    elif generation_type == "mix":
                        wait_time = 25

                    time.sleep(wait_time)

                    if os.path.exists(output_file):
                        if prompt['upload_to_s3']:
                            s3_url = upload_to_s3(output_file, s3_file_path)
                            if s3_url:
                                update_image_filename(prompt['id'], s3_url)
                        else:
                            update_image_filename(prompt['id'], output_file, False)
                    else:
                        print(f"Image generation not yet ready for prompt {prompt['id']}")
                        update_render_status(prompt['id'], 3)

            except Exception as e:
                print(f"Error processing prompt {prompt['id']}: {e}")
                update_render_status(prompt['id'], 4)

    except Exception as e:
        print(f"Error in generate_images_from_api: {e}")


if __name__ == "__main__":
    while True:
        generate_images_from_api()
        time.sleep(5)
