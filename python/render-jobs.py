import json
from urllib import request
import mysql.connector
import time
import random
import os
import requests
import json
from dotenv import load_dotenv
from pathlib import Path

current_dir = Path(__file__).resolve().parent
env_path = current_dir.parent / '.env'
load_dotenv(env_path)


# MySQL connection setup
db_config = {
    'host': os.getenv('DB_HOST', 'localhost'),
    'user': os.getenv('DB_USERNAME', 'root'),
    'password': os.getenv('DB_PASSWORD'),
    'database': os.getenv('DB_DATABASE')
}

OUTPUT_DIR = os.getenv('OUTPUT_DIR')
OPENROUTER_API_KEY = os.getenv('OPEN_ROUTER_API_KEY')
YOUR_SITE_URL = os.getenv('OPEN_ROUTER_YOUR_SITE_URL')
YOUR_APP_NAME = os.getenv('OPEN_ROUTER_YOUR_APP_NAME')
MODEL = os.getenv('OPEN_ROUTER_MODEL')

def get_image_filename(id):
    """Get the full filename including subfolder path of an existing image for the given id"""
    id_prefix = f"{id}_"

    # Walk through all directories and subdirectories
    for root, dirs, files in os.walk(OUTPUT_DIR):
        for filename in files:
            if filename.startswith(id_prefix) and filename.lower().endswith('.png'):
                # Get relative path from OUTPUT_DIR
                rel_path = os.path.relpath(root, OUTPUT_DIR)
                if rel_path == ".":
                    # If file is in root OUTPUT_DIR, just return filename
                    return filename
                else:
                    # Return path/filename
                    return os.path.join(rel_path, filename)
    return None


def update_image_filename(conn, id, filepath):
    """Update or insert record in yazi_ana_resim table with full filepath"""
    try:
        cursor = conn.cursor()

        update_query = "UPDATE prompts SET filename = %s WHERE id = %s"
        cursor.execute(update_query, (filepath, id))

        conn.commit()
        cursor.close()
        print(f"Updated prompts table for id {id} with path {filepath}")

    except mysql.connector.Error as err:
        print(f"Error updating prompts table: {err}")


def image_exists(id):
    """Check if image exists in any subfolder"""
    id_prefix = f"{id}_"

    if not os.path.exists(OUTPUT_DIR):
        return False

    # Walk through all directories and subdirectories
    for root, dirs, files in os.walk(OUTPUT_DIR):
        for filename in files:
            if filename.startswith(id_prefix) and filename.lower().endswith('.png'):
                return True
    return False


def safe_str(value):
    # Convert None to empty string and ensure string type
    return str(value) if value is not None else ""

def queue_prompt(prompt):
    p = {"prompt": prompt}
    data = json.dumps(p).encode('utf-8')
    req = request.Request("http://127.0.0.1:8188/prompt", data=data)
    request.urlopen(req)

def generate_images_from_database():
    try:
        # Load the base prompt
        prompt = json.loads(prompt_text_schnell_fp8)

        # Connect to MySQL
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor()

        # Query to fetch tanitim data for this category
        query = """SELECT id,generated_prompt,width,height
                  FROM prompts
                  WHERE isnull(filename)
                  ORDER BY id DESC
                  """
        cursor.execute(query)

        # Process each row
        for idx, (id,generated_prompt,width,height) in enumerate(cursor.fetchall()):
            print(f"Processing prompt {idx + 1} id: {id}")

            # Skip if generated_prompt is empty or None
            if not generated_prompt:
                continue

            # Check if image already exists
            if image_exists(id):
                print(f"Image for ID {id} already exists, updating database...")
                filename = get_image_filename(id)
                if filename:
                    update_ana_resim_table(conn, id, filename)
                continue

            prompt["6"]["inputs"]["text"] = generated_prompt
            prompt["9"]["inputs"]["filename_prefix"] = safe_str(id)
            prompt["31"]["inputs"]["seed"] = random.randint(1, 2**32)

            # Queue the prompt
            try:
                queue_prompt(prompt)
                print(f"Queued prompt for: {sanitized_text}...")
                time.sleep(30)

            except Exception as e:
                print(f"Error queuing prompt: {e}")

    except mysql.connector.Error as err:
        print(f"Database error: {err}")

    finally:
        if 'conn' in locals() and conn.is_connected():
            cursor.close()
            conn.close()
            print("Database connection closed.")

if __name__ == "__main__":
    import sys

    generate_images_from_database()
