import json
import time
import random
import os
from pathlib import Path
from dotenv import load_dotenv

import vertexai
from vertexai.preview.vision_models import ImageGenerationModel
from google.oauth2 import service_account

current_dir = Path(__file__).resolve().parent
env_path = current_dir.parent / '.env'
load_dotenv(env_path)

credentials = service_account.Credentials.from_service_account_file(
 os.getenv('GOOGLE_AUTH_KEY_PATH',"google.json"),
 scopes=["https://www.googleapis.com/auth/cloud-platform"],)

output_file = "generated_image_" + str(int(time.time())) + ".png"
prompt = "A vibrant jazz album cover where a shiny apple takes center stage amidst evocative abstractions of saxophones and trumpets...."


vertexai.init(project=os.getenv('GOOGLE_PROJECT_ID',""), location="us-central1", credentials=credentials)
model = ImageGenerationModel.from_pretrained("imagen-3.0-generate-002")

images = model.generate_images(
 prompt=prompt,
 number_of_images=1,
 language="en",
 add_watermark=False,
 # seed=100,
 aspect_ratio="1:1",
 safety_filter_level="block_only_high",
 person_generation="allow_adult",
)

images[0].save(location=output_file, include_generation_parameters=False)

# Optional. View the generated image in a notebook.
# images[0].show()

print(f"Created output image using {len(images[0]._image_bytes)} bytes")
# Example response:
# Created output image using 1234567 bytes
