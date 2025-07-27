import argparse
import json
import os
import requests
import shutil
import sys
import tempfile
from fpdf import FPDF

# ==============================================================================
# --- PDF Class (accepts config via constructor) ---
# ==============================================================================
class StorybookPDF(FPDF):
    """
    Custom PDF class with configurable page size, rounded borders, and footers.
    """
    def __init__(self, width, height, dpi, font_name):
        super().__init__(orientation='P', unit='mm', format=(width, height))
        self.dpi = dpi
        self.font_name = font_name
        self.set_auto_page_break(auto=True, margin=15)
        self.show_footer = False
        self.logical_page_number = 0

    def footer(self):
        if self.show_footer:
            self.set_y(-20)
            self.set_font(self.font_name, 'I', 10)
            self.set_text_color(128, 128, 128)
            self.cell(0, 10, f'{self.logical_page_number}', 0, 0, 'C')

    def draw_rounded_dotted_border(self, margin=10, radius=10):
        self.set_draw_color(180, 180, 180)
        self.set_line_width(0.3)
        self.set_fill_color(255, 255, 255)
        self.set_dash_pattern(dash=1, gap=1)

        x, y = margin, margin
        w, h = self.w - 2 * margin, self.h - 2 * margin
        r = radius
        k = 0.552284749831 # Bezier curve constant

        with self.new_path() as path:
            path.move_to(x + r, y)
            path.line_to(x + w - r, y)
            path.curve_to(x + w - r + (r * k), y, x + w, y + r - (r * k), x + w, y + r)
            path.line_to(x + w, y + h - r)
            path.curve_to(x + w, y + h - r + (r * k), x + w - r + (r * k), y + h, x + w - r, y + h)
            path.line_to(x + r, y + h)
            path.curve_to(x + r - (r * k), y + h, x, y + h - r + (r * k), x, y + h - r)
            path.line_to(x, y + r)
            path.curve_to(x, y + r - (r * k), x + r - (r * k), y, x + r, y)
            path.close()

        self.draw_path(path)
        self.set_dash_pattern() # Reset dash pattern

# ==============================================================================
# --- Helper Functions ---
# ==============================================================================
def download_image(url, folder, page_num):
    """Downloads an image from a URL into a specified folder."""
    if not url:
        print(f"Warning: No image URL for page {page_num}.")
        return None
    try:
        response = requests.get(url, stream=True, timeout=30)
        response.raise_for_status()

        # Determine file extension from URL or content type
        content_type = response.headers.get('content-type', '').lower()
        if 'jpeg' in content_type or 'jpg' in content_type:
            ext = '.jpg'
        elif 'png' in content_type:
            ext = '.png'
        else:
            # Fallback to extension from URL path
            _, url_ext = os.path.splitext(url)
            ext = url_ext if url_ext in ['.jpg', '.jpeg', '.png'] else '.jpg'

        filename = f"{page_num}{ext}"
        filepath = os.path.join(folder, filename)

        with open(filepath, 'wb') as f:
            for chunk in response.iter_content(chunk_size=8192):
                f.write(chunk)

        print(f"Downloaded image for page {page_num} to {filepath}")
        return filepath
    except requests.exceptions.RequestException as e:
        print(f"Error downloading image for page {page_num} from {url}: {e}", file=sys.stderr)
        return None

# ==============================================================================
# --- Main PDF Generation Logic ---
# ==============================================================================
def create_storybook_pdf(args, story_data):
    """Generates the complete storybook PDF from arguments and story data."""

    pdf = StorybookPDF(
        width=args.width_mm,
        height=args.height_mm,
        dpi=args.dpi,
        font_name=args.font_name
    )

    # --- Font Setup ---
    try:
        pdf.add_font(args.font_name, '', args.font_file)
        pdf.add_font(args.font_name, 'I', args.font_file) # Italic style
    except RuntimeError as e:
        print(f"ERROR: Font '{args.font_file}' not found or failed to load. {e}", file=sys.stderr)
        # Use a fallback font that fpdf2 knows
        pdf.font_name = "Arial"
        args.font_name = "Arial"

    # --- Title Page ---
    pdf.add_page()
    pdf.set_font(args.font_name, '', 24)
    pdf.set_text_color(30, 30, 100)
    pdf.set_y(pdf.h / 2 - 20) # Center vertically
    pdf.cell(0, 10, story_data.get("title", "Untitled Story"), align='C')
    pdf.ln(15)
    pdf.set_font(args.font_name, '', 14)
    pdf.set_text_color(0)
    pdf.cell(0, 10, story_data.get("subtitle", ""), align='C')
    pdf.show_footer = False

    # --- Create a temporary directory for downloaded images ---
    image_temp_dir = tempfile.mkdtemp(prefix="storybook_images_")
    print(f"Created temporary image directory: {image_temp_dir}")

    try:
        # --- Text and Image Pages ---
        for i, page_data in enumerate(story_data.get("pages", []), start=1):
            text = page_data.get("text", "")
            image_url = page_data.get("image_url")

            # --- TEXT PAGE ---
            pdf.add_page()
            pdf.logical_page_number += 1

            if args.wallpaper_file and os.path.exists(args.wallpaper_file):
                pdf.image(args.wallpaper_file, x=0, y=0, w=pdf.w, h=pdf.h)

            pdf.draw_rounded_dotted_border(margin=10, radius=10)
            pdf.set_font(args.font_name, '', 14)
            pdf.set_text_color(0)

            # Vertical Centering Logic
            border_margin = 10
            text_area_height = pdf.h - (2 * border_margin)
            cell_width = pdf.w - (2 * border_margin) - 20 # Inner padding
            line_height = 8

            lines = pdf.multi_cell(w=cell_width, h=line_height, text=text, align='C', split_only=True)
            text_block_height = len(lines) * line_height
            y_start = (text_area_height - text_block_height) / 2 + border_margin
            pdf.set_xy((pdf.w - cell_width) / 2, y_start)
            pdf.multi_cell(w=cell_width, h=line_height, text=text, align='C')
            pdf.show_footer = True

            # --- IMAGE PAGE ---
            pdf.add_page()
            image_path = download_image(image_url, image_temp_dir, i)
            if image_path and os.path.exists(image_path):
                pdf.image(image_path, x=0, y=0, w=pdf.w, h=pdf.h)
            else:
                pdf.set_font(args.font_name, '', 14)
                pdf.set_xy(0, pdf.h / 2 - 5)
                pdf.cell(0, 10, f"Image for page {i} could not be loaded.", align='C')
            pdf.show_footer = False

        pdf.output(args.output_file)
        print(f"\nPDF successfully created: {args.output_file}")

    finally:
        # --- Clean up temporary image directory ---
        # shutil.rmtree(image_temp_dir)
        print(f"Removed temporary image directory: {image_temp_dir}")


# ==============================================================================
# --- Script Entry Point ---
# ==============================================================================
if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Generate a storybook PDF from JSON data.")
    parser.add_argument("--data-file", required=True, help="Path to the JSON file containing story data.")
    parser.add_argument("--output-file", required=True, help="Path to save the generated PDF file.")
    parser.add_argument("--width-mm", required=True, type=float, help="Page width in millimeters.")
    parser.add_argument("--height-mm", required=True, type=float, help="Page height in millimeters.")
    parser.add_argument("--dpi", required=True, type=int, help="DPI for image processing.")
    parser.add_argument("--font-name", required=True, help="Logical name for the font (e.g., 'LoveYaLikeASister').")
    parser.add_argument("--font-file", required=True, help="Path to the .ttf font file.")
    parser.add_argument("--wallpaper-file", help="Optional path to the wallpaper image for text pages.")

    args = parser.parse_args()

    # 1. Load story data from JSON file
    try:
        with open(args.data_file, "r", encoding="utf-8") as f:
            story_data = json.load(f)
    except FileNotFoundError:
        print(f"FATAL ERROR: JSON data file not found at '{args.data_file}'", file=sys.stderr)
        sys.exit(1)
    except json.JSONDecodeError:
        print(f"FATAL ERROR: Could not parse the JSON file '{args.data_file}'.", file=sys.stderr)
        sys.exit(1)

    if not story_data.get("pages"):
        print("Warning: JSON data contains no pages. Exiting.", file=sys.stderr)
        sys.exit(0)

    # 2. Generate the PDF
    create_storybook_pdf(args, story_data)
