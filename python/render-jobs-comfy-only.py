import json
from urllib import request
import time
import random
import os
import requests
import json
import boto3
from dotenv import load_dotenv
from shutil import copyfile
import tempfile
import math
from pathlib import Path


current_dir = Path(__file__).resolve().parent
env_path = current_dir.parent / '.env'
load_dotenv(env_path)

# Existing environment variables
API_BASE_URL = "http://localhost:8011/api"
# API_BASE_URL = os.getenv('API_BASE_URL')

OUTPUT_DIR = os.getenv('OUTPUT_DIR')
COMFY_DEFAULT_OUTPUT_DIR = os.getenv('COMFY_DEFAULT_OUTPUT_DIR')
MOVE_TO_DIR = os.getenv('MOVE_TO_DIR')
OPENROUTER_API_KEY = os.getenv('OPEN_ROUTER_API_KEY')
YOUR_SITE_URL = "http://localhost:8011" # os.getenv('OPEN_ROUTER_YOUR_SITE_URL')
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


def queue_prompt(prompt, prompt_id):
    p = {"prompt": prompt, "prompt_id": prompt_id}
    data = json.dumps(p).encode('utf-8')
    req = request.Request("http://127.0.0.1:8188/prompt", data=data)
    request.urlopen(req)

def get_history(prompt_id):
    with request.urlopen("http://127.0.0.1:8188/history/{}".format(prompt_id)) as response:
        return json.loads(response.read())


def get_workflow_file(generation_type, model):
    """Get the appropriate workflow file based on type"""
    workflow_file = ""
    if generation_type == "prompt":
        if model == "schnell":
            workflow_file = "flux_schnell_for_image_gen.json"
        elif model == "dev":
            workflow_file = "flux_dev_for_image_gen.json"
    elif generation_type == "outpaint":
        workflow_file = "flux_outpaint_for_image_gen.json"
    elif generation_type == "mix-one":
        workflow_file = "flux_one_image_mix_for_image_gen.json"
    elif generation_type == "mix":
        workflow_file = "flux_two_image_mix_for_image_gen.json"
    elif generation_type == "kontext-basic":
        workflow_file = "flux_kontext_basic.json"
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

prompt_status_counter = {}

def generate_images_from_api():
    global prompt_status_counter

    try:
        print("Starting image generation from API...")
        response = requests.get(f"{API_BASE_URL}/prompts/pending")
        if response.status_code != 200:
            print(f"Error fetching prompts: {response.status_code} {response.json()}")
            return

        prompts = response.json()['prompts']

        for idx, prompt in enumerate(prompts):

            prompt_id = prompt['id']
            prompt_id_str = str(prompt_id)
            render_status = prompt['render_status']
            generation_type = prompt['generation_type']
            model = prompt['model']

            if generation_type in ["prompt", "mix", "mix-one", "kontext-basic"] and model in ["schnell", "dev"]:
                pass
            else:
                print(f"Skipping prompt {prompt_id} - not local model")
                continue


            print(f"Processing prompt {idx + 1} id: {prompt_id} - type: {prompt['generation_type']} - model: {prompt['model']} - status: {render_status} - user id: {prompt['user_id']}")

            try:
                if generation_type in ["kontext-basic"]:
                    prompt_history = get_history(prompt_id)
                    print(f"Prompt History: {prompt_history}")
                    if prompt_history:
                        node_info = prompt_history.get(prompt_id_str, {})
                        outputs = node_info.get('outputs', {})
                        output_136 = outputs.get('136', {})
                        images = output_136.get('images', []) # Default to an empty list for the images

                        # Now, check if the 'images' list is not empty before accessing the first element
                        if images:
                            # We can now safely access the first element and its 'filename' key
                            image_data = images[0]
                            output_filename = image_data.get('filename')

                            if output_filename:
                                output_file = str(Path(COMFY_DEFAULT_OUTPUT_DIR) / output_filename)
                                print(f"Successfully found output file: {output_file}")
                            else:
                                print("Image data found, but 'filename' key is missing.")
                                output_filename = "kontext-not-ready.png"
                                output_file = str(Path(COMFY_DEFAULT_OUTPUT_DIR) / output_filename)
                        else:
                            print("Could not find the required path or the 'images' list was empty.")
                            output_filename = "kontext-not-ready.png"
                            output_file = str(Path(COMFY_DEFAULT_OUTPUT_DIR) / output_filename)
                    else:
                        print(f"No history found for prompt {prompt_id}, skipping...")
                        output_filename = "kontext-not-ready.png"
                        output_file = str(Path(COMFY_DEFAULT_OUTPUT_DIR) / output_filename)
                else:
                    output_filename = f"{generation_type}_{model}_{prompt_id}_{prompt['user_id']}.png"
                    output_file = str(Path(OUTPUT_DIR) / output_filename)

                s3_file_path = f"images/{output_filename}"

                Path(OUTPUT_DIR).mkdir(parents=True, exist_ok=True)

                if render_status in (1, 3):

                    skip_continue = False
                    prompt_status_counter[prompt_id] = prompt_status_counter.get(prompt_id, 0) + 1
                    if prompt_status_counter[prompt_id] > 20:
                        # skip_continue = True
                        print(f"Skipping prompt {prompt_id} - seen with status 1 more than 20 times, try render again")
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
                    if model == "schnell":
                        workflow = get_workflow_file(generation_type,model)
                        workflow["6"]["inputs"]["text"] = prompt['generated_prompt']
                        workflow["25"]["inputs"]["noise_seed"] = random.randint(1, 2**32)
                        workflow["31"]["inputs"]["file_name_template"] = f"{generation_type}_{model}_{prompt_id}_{prompt['user_id']}.png"
                        workflow["5"]["inputs"]["width"] = prompt['width']
                        workflow["5"]["inputs"]["height"] = prompt['height']

                    elif model == "dev":
                        workflow = get_workflow_file(generation_type,model)
                        workflow["6"]["inputs"]["text"] = prompt['generated_prompt']
                        workflow["25"]["inputs"]["noise_seed"] = random.randint(1, 2**32)
                        workflow["41"]["inputs"]["file_name_template"] = f"{generation_type}_{model}_{prompt_id}_{prompt['user_id']}.png"
                        workflow["27"]["inputs"]["width"] = prompt['width']
                        workflow["27"]["inputs"]["height"] = prompt['height']
                        workflow["30"]["inputs"]["width"] = prompt['width']
                        workflow["30"]["inputs"]["height"] = prompt['height']
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

                    workflow = get_workflow_file(generation_type,model)

                    # load source image from absolute path
                    workflow["40"]["inputs"]["image"] = image1_path
                    workflow["56"]["inputs"]["image"] = image2_path

                    workflow["54"]["inputs"]["downsampling_factor"] = prompt.get('input_image_1_strength', 1)
                    workflow["55"]["inputs"]["downsampling_factor"] = prompt.get('input_image_2_strength', 1)

                    workflow["6"]["inputs"]["text"] = prompt['generated_prompt']
                    workflow["25"]["inputs"]["noise_seed"] = random.randint(1, 2**32)
                    workflow["57"]["inputs"]["file_name_template"] = f"{generation_type}_{model}_{prompt_id}_{prompt['user_id']}.png"

                    # postprocessing resize width and height (proportional)
                    workflow["27"]["inputs"]["width"] = prompt['width']
                    workflow["27"]["inputs"]["height"] = prompt['height']

                    workflow["30"]["inputs"]["width"] = prompt['width']
                    workflow["30"]["inputs"]["height"] = prompt['height']

                    print("Debugging mix prompt:")
                    print (f"Using images with strengths: {prompt.get('input_image_1', 1)} and {prompt.get('input_image_2', 1)} :: {prompt.get('input_image_1_strength', 1)} and {prompt.get('input_image_2_strength', 1)}")
                    print (f"Prompt and width and height: {prompt['generated_prompt']} :: {prompt['width']} and {prompt['height']}")
                elif generation_type == "mix-one":
                    temp_dir = tempfile.mkdtemp()

                    # Download images
                    image1_path = os.path.join(temp_dir, "image1.png")

                    # Download image 1
                    if not download_image(prompt['input_image_1'], image1_path):
                        raise Exception(f"Failed to download image 1 from {prompt['input_image_1']}")

                    workflow = get_workflow_file(generation_type,model)

                    # load source image from absolute path
                    workflow["40"]["inputs"]["image"] = image1_path

                    strength_int = prompt.get('input_image_1_strength', 1)
                    if strength_int == 1:
                        strength_str = "highest"
                    elif strength_int == 2:
                        strength_str = "high"
                    elif strength_int == 3:
                        strength_str = "medium"
                    elif strength_int == 4:
                        strength_str = "low"
                    elif strength_int == 5:
                        strength_str = "lowest"
                    workflow["54"]["inputs"]["image_strength"] = strength_str

                    workflow["6"]["inputs"]["text"] = prompt['generated_prompt']
                    workflow["25"]["inputs"]["noise_seed"] = random.randint(1, 2**32)
                    workflow["56"]["inputs"]["file_name_template"] = f"{generation_type}_{model}_{prompt_id}_{prompt['user_id']}.png"

                    # postprocessing resize width and height (proportional)
                    workflow["27"]["inputs"]["width"] = prompt['width']
                    workflow["27"]["inputs"]["height"] = prompt['height']

                    workflow["30"]["inputs"]["width"] = prompt['width']
                    workflow["30"]["inputs"]["height"] = prompt['height']

                    print("Debugging mix-one prompt:")
                    print (f"Using image with strength: {prompt.get('input_image_1', 1)} :: {prompt.get('input_image_1_strength', 1)}")
                    print (f"Prompt and width and height: {prompt['generated_prompt']} :: {prompt['width']} and {prompt['height']}")
                elif generation_type == "kontext-basic":
                    temp_dir = tempfile.mkdtemp()

                    # Download images
                    image1_path = os.path.join(temp_dir, "image1.png")

                    # Download image 1
                    if not download_image(prompt['input_image_1'], image1_path):
                        raise Exception(f"Failed to download image 1 from {prompt['input_image_1']}")

                    workflow = get_workflow_file(generation_type,model)

                    # load source image from absolute path
                    workflow["142"]["inputs"]["image"] = image1_path

                    workflow["6"]["inputs"]["text"] = prompt['generated_prompt']
                    workflow["31"]["inputs"]["seed"] = random.randint(1, 2**32)

                if os.path.exists(output_file):
                    print(f"Image exists for prompt {prompt_id}, uploading to S3...")
                    if prompt['upload_to_s3']:
                        s3_url = upload_to_s3(output_file, s3_file_path)
                        if s3_url:
                            update_image_filename(prompt_id, s3_url)
                    else:
                        update_image_filename(prompt_id, output_file, False)
                else:
                    print(f"Rendering image for prompt {prompt_id}")
                    queue_prompt(workflow, prompt_id)
                    update_render_status(prompt_id, 1)
                    print(f"Queued prompt for: {prompt['generated_prompt']}...")

                    time.sleep(5)


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
