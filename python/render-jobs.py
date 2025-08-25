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

# New import for cross-platform timeouts
from pebble import ProcessPool
from concurrent.futures import TimeoutError

#import vertexai
#from vertexai.preview.vision_models import ImageGenerationModel
#from google.oauth2 import service_account
#from google.api_core.exceptions import GoogleAPIError
#import traceback

import fal_client

# --- Environment Variable Loading ---
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

# Configurable timeout for Fal.ai calls
FAL_TIMEOUT = int(os.getenv('FAL_TIMEOUT', 180)) # Timeout in seconds (e.g., 3 minutes)

# --- S3 Client Initialization ---
s3_client = boto3.client(
    's3',
    aws_access_key_id=AWS_ACCESS_KEY_ID,
    aws_secret_access_key=AWS_SECRET_ACCESS_KEY,
    region_name=AWS_REGION
)

# --- Helper Functions ---

def get_aspect_ratio(width, height):
    """Find the closest standard aspect ratio string."""
    standard_ratios = {
        "1:1": 1.0, "16:9": 16/9, "4:3": 4/3, "3:2": 3/2,
        "2:3": 2/3, "3:4": 3/4, "9:16": 9/16, "21:9": 21/9
    }
    if height == 0:
        return "1:1"
    actual_ratio = width / height
    closest_ratio = min(standard_ratios.items(), key=lambda x: abs(x[1] - actual_ratio))
    return closest_ratio[0]

def download_image(url, output_path):
    """Download an image from a URL to a local path."""
    try:
        response = requests.get(url, stream=True, timeout=60) # Add timeout to download
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
    """Upload a file to S3 and return the CloudFront URL."""
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
    """Update record via API with the final image path or URL."""
    try:
        response = requests.post(f"{API_BASE_URL}/prompts/update-filename", json={
            'id': id,
            'filename': file_path
        })
        if response.status_code == 200:
            print(f"Updated prompts table for id {id} with path {file_path}")
        else:
            print(f"Error updating prompt filename: {response.json()}")
    except Exception as err:
        print(f"Error updating prompt filename via API: {err}")

def update_render_status(id, status):
    """Update render status via API (e.g., pending, failed)."""
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

# This is the target function that will run in a separate process
def fal_subscribe_task(model_name, arguments):
    """Wrapper for the blocking fal_client call."""
    return fal_client.subscribe(
        model_name,
        arguments=arguments,
        with_logs=False,
    )

def generate_with_fal(model_name, arguments):
    """
    Calls fal_client.subscribe in a separate process with a timeout.
    This is cross-platform compatible (works on Windows, Linux, macOS).
    """
    print(f"Sending to Fal/{model_name} with a {FAL_TIMEOUT}s timeout...")
    with ProcessPool() as pool:
        # Schedule the task to run in the background process pool
        future = pool.schedule(fal_subscribe_task, args=[model_name, arguments])
        try:
            # Wait for the result, with a timeout
            result = future.result(timeout=FAL_TIMEOUT)
            return result
        except TimeoutError:
            print(f"ERROR: Timeout calling {model_name} after {FAL_TIMEOUT} seconds.")
            return None
        except Exception as e:
            print(f"ERROR: An unexpected error occurred in the fal_client process for {model_name}: {e}")
            return None

# --- Main Processing Logic ---

prompt_status_counter = {}

def generate_images_from_api():
    global prompt_status_counter

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

            # Define remote models handled by this script
            remote_fal_models = {
                "imagen3": "fal-ai/imagen4/preview/ultra",
                "aura-flow": "fal-ai/aura-flow",
                "ideogram-v2a": "fal-ai/ideogram/v2a",
                "luma-photon": "fal-ai/luma-photon",
                "recraft-20b": "fal-ai/recraft-20b",
                "fal-ai/qwen-image": "fal-ai/qwen-image"
            }
            remote_other_models = ["minimax", "minimax-expand"]

            if generation_type != "prompt" or (model not in remote_fal_models and model not in remote_other_models):
                # This print can be noisy, optionally comment it out
                # print(f"Skipping prompt {prompt_id} - not a remote model for this worker.")
                continue

            print(f"Processing prompt {idx + 1} id: {prompt_id} - type: {generation_type} - model: {model} - status: {render_status} - user id: {prompt['user_id']}")

            try:
                output_filename = f"{generation_type}_{model.replace('/', '-')}_{prompt_id}_{prompt['user_id']}.png"
                output_file = str(Path(OUTPUT_DIR) / output_filename)
                s3_file_path = f"images/{output_filename}"
                Path(OUTPUT_DIR).mkdir(parents=True, exist_ok=True)

                if render_status in (1, 3):
                    prompt_status_counter[prompt_id] = prompt_status_counter.get(prompt_id, 0) + 1
                    if prompt_status_counter[prompt_id] > 20:
                        print(f"Prompt {prompt_id} has been stuck for too long. Marking as failed.")
                        update_render_status(prompt_id, 4)
                        del prompt_status_counter[prompt_id]
                        continue

                    if os.path.exists(output_file):
                        print(f"Found existing image for prompt {prompt_id}, attempting re-upload.")
                        if prompt['upload_to_s3']:
                            s3_url = upload_to_s3(output_file, s3_file_path)
                            if s3_url:
                                update_image_filename(prompt_id, s3_url)
                        else:
                            update_image_filename(prompt_id, output_file, False)
                    continue

                # --- Image Generation Logic ---
                first_image_url = None

                if model in remote_fal_models:
                    fal_model_name = remote_fal_models[model]
                    arguments = {"prompt": prompt['generated_prompt']}
                    if model == "fal-ai/qwen-image":
                        arguments["image_size"] = {"width": prompt['width'], "height": prompt['height']}

                    fal_result = generate_with_fal(fal_model_name, arguments)

                    if fal_result and "images" in fal_result and len(fal_result["images"]) > 0:
                        first_image_url = fal_result["images"][0]["url"]
                    else:
                        print(f"Fal.ai call failed or returned no images for model {model}.")
                        update_render_status(prompt_id, 4)
                        continue

                elif model in ["minimax", "minimax-expand"]:
                    print(f"Sending to Minimax: {prompt['generated_prompt']}...")
                    payload = json.dumps({
                        "model": "image-01",
                        "prompt": prompt['generated_prompt'],
                        "aspect_ratio": get_aspect_ratio(prompt['width'], prompt['height']),
                        "response_format": "url",
                        "n": 1,
                        "prompt_optimizer": (model == "minimax-expand")
                    })
                    headers = {
                        'Authorization': f'Bearer {os.getenv("MINIMAX_KEY")}',
                        'Content-Type': 'application/json'
                    }
                    response = requests.post(os.getenv("MINIMAX_KEY_URL"), headers=headers, data=payload, timeout=120)
                    response.raise_for_status()
                    response_json = response.json()
                    first_image_url = response_json["data"]["image_urls"][0]

                # --- Download, Save, and Upload ---
                if first_image_url:
                    if download_image(first_image_url, output_file):
                        if prompt['upload_to_s3']:
                            s3_url = upload_to_s3(output_file, s3_file_path)
                            if s3_url:
                                update_image_filename(prompt_id, s3_url)
                            else:
                                print(f"S3 upload failed for prompt {prompt_id}.")
                                update_render_status(prompt_id, 4)
                        else:
                            update_image_filename(prompt_id, output_file, False)
                    else:
                        print(f"Failed to download the generated image for prompt {prompt_id}.")
                        update_render_status(prompt_id, 4)

                time.sleep(6) # Keep a small delay between jobs

            except Exception as e:
                print(f"CRITICAL ERROR processing prompt {prompt_id}: {e}")
                update_render_status(prompt_id, 4)

    except Exception as e:
        print(f"CRITICAL ERROR in main loop generate_images_from_api: {e}")


if __name__ == "__main__":
    while True:
        generate_images_from_api()
        time.sleep(5)
