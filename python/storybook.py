import argparse
import json
import os
import requests
import shutil
import sys
import tempfile
from fpdf import FPDF
from PIL import Image

def hex_to_rgb(hex_color):
    """Converts a hex color string (e.g., #RRGGBB) to an (R, G, B) tuple."""
    hex_color = hex_color.lstrip('#')
    if len(hex_color) != 6:
        print(f"Warning: Invalid hex color '{hex_color}'. Using black.", file=sys.stderr)
        return 0, 0, 0
    try:
        return tuple(int(hex_color[i:i+2], 16) for i in (0, 2, 4))
    except ValueError:
        print(f"Warning: Could not parse hex color '{hex_color}'. Using black.", file=sys.stderr)
        return 0, 0, 0

# ==============================================================================
# --- PDF Class (accepts config via constructor) ---
# ==============================================================================
class StorybookPDF(FPDF):
    """
    Custom PDF class with configurable page size, rounded borders, and footers.
    Handles bleed dimensions.
    """
    def __init__(self, width, height, dpi, font_name, bleed_mm, footer_font_size, footer_color):
        super().__init__(orientation='P', unit='mm', format=(width + 2 * bleed_mm, height + 2 * bleed_mm))
        self.dpi = dpi
        self.font_name = font_name
        self.bleed_mm = bleed_mm
        self.trim_width = width
        self.trim_height = height
        self.footer_font_size = footer_font_size
        self.footer_color = footer_color
        self.set_auto_page_break(auto=True, margin=self.bleed_mm + 15)
        self.show_footer = False
        self.logical_page_number = 0

    def footer(self):
        if self.show_footer:
            self.set_y(self.bleed_mm + self.trim_height - 15)
            self.set_font(self.font_name, 'I', self.footer_font_size)
            self.set_text_color(*self.footer_color)
            self.set_x(self.bleed_mm)
            self.cell(self.trim_width, 10, f'{self.logical_page_number}', 0, 0, 'C')

    # START MODIFICATION: Refactor border drawing to accept coordinates.
    def draw_rounded_dotted_border(self, x, y, w, h, radius=10):
        """Draws a rounded border at a specific location."""
        self.set_draw_color(180, 180, 180)
        self.set_line_width(0.3)
        self.set_fill_color(255, 255, 255)
        self.set_dash_pattern(dash=1, gap=1)
        k = 0.552284749831

        with self.new_path() as path:
            path.move_to(x + radius, y)
            path.line_to(x + w - radius, y)
            path.curve_to(x + w - radius + (radius * k), y, x + w, y + radius - (radius * k), x + w, y + radius)
            path.line_to(x + w, y + h - radius)
            path.curve_to(x + w, y + h - radius + (radius * k), x + w - radius + (radius * k), y + h, x + w - radius, y + h)
            path.line_to(x + radius, y + h)
            path.curve_to(x + radius - (radius * k), y + h, x, y + h - radius + (radius * k), x, y + h - radius)
            path.line_to(x, y + radius)
            path.curve_to(x, y + radius - (radius * k), x + radius - (radius * k), y, x + radius, y)
            path.close()

        self.draw_path(path)
        self.set_dash_pattern()
    # END MODIFICATION

# ==============================================================================
# --- Helper Functions ---
# ==============================================================================
def download_and_convert_image(url, folder, page_num):
    """Downloads an image from a URL, converts it to JPG, and saves it."""
    if not url:
        print(f"Warning: No image URL for page {page_num}.")
        return None
    try:
        headers = {'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36'}
        response = requests.get(url, stream=True, timeout=30, headers=headers)
        response.raise_for_status()

        with Image.open(response.raw) as im:
            jpg_path = os.path.join(folder, f"{page_num}.jpg")
            if im.mode == 'RGBA':
                background = Image.new("RGB", im.size, (255, 255, 255))
                background.paste(im, mask=im.getchannel('A'))
                im = background
            elif im.mode != 'RGB':
                im = im.convert('RGB')
            im.save(jpg_path, 'JPEG', quality=95)
            print(f"Downloaded and converted image for page {page_num} to {jpg_path}")
            return jpg_path

    except requests.exceptions.RequestException as e:
        print(f"Error downloading image for page {page_num} from {url}: {e}", file=sys.stderr)
        return None
    except Exception as e:
        print(f"Error processing image for page {page_num} from {url}: {e}", file=sys.stderr)
        return None

def draw_crop_marks(pdf):
    """Draws crop marks at the corners of the trim box."""
    if pdf.bleed_mm <= 0:
        return

    bleed = pdf.bleed_mm
    trim_x1, trim_y1 = bleed, bleed
    trim_x2, trim_y2 = pdf.w - bleed, pdf.h - bleed
    mark_len = min(bleed * 0.75, 5)

    pdf.set_draw_color(0, 0, 0)
    pdf.set_line_width(0.1)

    pdf.line(trim_x1, 0, trim_x1, mark_len)
    pdf.line(0, trim_y1, mark_len, trim_y1)
    pdf.line(trim_x2, 0, trim_x2, mark_len)
    pdf.line(pdf.w - mark_len, trim_y1, pdf.w, trim_y1)
    pdf.line(trim_x1, pdf.h - mark_len, trim_x1, pdf.h)
    pdf.line(0, trim_y2, mark_len, trim_y2)
    pdf.line(trim_x2, pdf.h - mark_len, trim_x2, pdf.h)
    pdf.line(pdf.w - mark_len, trim_y2, pdf.w, trim_y2)

# ==============================================================================
# --- Main PDF Generation Logic ---
# ==============================================================================
def create_storybook_pdf(args, story_data):
    """Generates the complete storybook PDF from arguments and story data."""

    pdf = StorybookPDF(
        width=args.width_mm,
        height=args.height_mm,
        dpi=args.dpi,
        font_name=args.font_name,
        bleed_mm=args.bleed_mm,
        footer_font_size=args.font_size_footer,
        footer_color=hex_to_rgb(args.color_footer)
    )

    try:
        pdf.add_font(args.font_name, '', args.font_file)
        pdf.add_font(args.font_name, 'I', args.font_file)
    except RuntimeError as e:
        print(f"ERROR: Font '{args.font_file}' not found or failed to load. {e}", file=sys.stderr)
        pdf.font_name = "Arial"
        args.font_name = "Arial"

    # START MODIFICATION: Position special pages according to right-hand page margins.
    content_width = pdf.trim_width - args.margin_inside_mm - args.margin_outside_mm
    content_x = pdf.bleed_mm + args.margin_inside_mm

    if args.title_page_text:
        pdf.add_page()
        pdf.set_font(args.font_name, '', args.font_size_title)
        pdf.set_text_color(*hex_to_rgb(args.color_title))
        pdf.set_xy(content_x, pdf.bleed_mm + args.margin_top_mm)
        pdf.multi_cell(w=content_width, h=15, text=args.title_page_text, align='C')
        if args.show_bleed_marks: draw_crop_marks(pdf)

    if args.copyright_text:
        pdf.add_page()
        pdf.set_font(args.font_name, '', args.font_size_copyright)
        pdf.set_text_color(*hex_to_rgb(args.color_copyright))
        pdf.set_xy(content_x, pdf.h - pdf.bleed_mm - args.margin_bottom_mm - 30)
        pdf.multi_cell(w=content_width, h=5, text=args.copyright_text, align='C')
        if args.show_bleed_marks: draw_crop_marks(pdf)

    if args.introduction_text:
        pdf.add_page()
        pdf.set_font(args.font_name, '', args.font_size_introduction)
        pdf.set_text_color(*hex_to_rgb(args.color_introduction))
        pdf.set_xy(content_x, pdf.bleed_mm + args.margin_top_mm)
        pdf.multi_cell(w=content_width, h=7, text=args.introduction_text, align='J')
        if args.show_bleed_marks: draw_crop_marks(pdf)
    # END MODIFICATION

    image_temp_dir = tempfile.mkdtemp(prefix="storybook_images_")
    print(f"Created temporary image directory: {image_temp_dir}")

    try:
        # START MODIFICATION: Apply mirrored margins to text pages.
        for i, page_data in enumerate(story_data.get("pages", []), start=1):
            text = page_data.get("text", "")
            image_url = page_data.get("image_url")

            # --- TEXT PAGE ---
            pdf.add_page()
            pdf.logical_page_number += 1
            pdf.show_footer = True

            is_odd_page = pdf.logical_page_number % 2 != 0
            margin_left = args.margin_inside_mm if is_odd_page else args.margin_outside_mm
            margin_right = args.margin_outside_mm if is_odd_page else args.margin_inside_mm

            if args.wallpaper_file and os.path.exists(args.wallpaper_file):
                pdf.image(args.wallpaper_file, x=0, y=0, w=pdf.w, h=pdf.h)

            border_x = pdf.bleed_mm + margin_left
            border_y = pdf.bleed_mm + args.margin_top_mm
            border_w = pdf.trim_width - margin_left - margin_right
            border_h = pdf.trim_height - args.margin_top_mm - args.margin_bottom_mm
            pdf.draw_rounded_dotted_border(border_x, border_y, border_w, border_h, radius=10)

            pdf.set_font(args.font_name, '', args.font_size_main)
            pdf.set_text_color(*hex_to_rgb(args.color_main))

            text_padding = 10
            text_x = border_x + text_padding
            text_w = border_w - (2 * text_padding)
            text_h = border_h - (2 * text_padding)
            line_height = 8 * (args.font_size_main / 14)

            lines = pdf.multi_cell(w=text_w, h=line_height, text=text, align='C', split_only=True)
            text_block_height = len(lines) * line_height
            y_centered_start = border_y + text_padding + (text_h - text_block_height) / 2
            pdf.set_xy(text_x, y_centered_start)
            pdf.multi_cell(w=text_w, h=line_height, text=text, align='C')

            if args.show_bleed_marks: draw_crop_marks(pdf)

            # --- IMAGE PAGE ---
            pdf.add_page()
            pdf.show_footer = False
            image_path = download_and_convert_image(image_url, image_temp_dir, i)
            if image_path and os.path.exists(image_path):
                pdf.image(image_path, x=0, y=0, w=pdf.w, h=pdf.h)
            else:
                pdf.set_font(args.font_name, '', 14)
                pdf.set_xy(0, pdf.h / 2 - 5)
                pdf.cell(0, 10, f"Image for page {i} could not be loaded.", align='C')

            if args.show_bleed_marks: draw_crop_marks(pdf)
        # END MODIFICATION

        pdf.output(args.output_file)
        print(f"\nPDF successfully created: {args.output_file}")

    finally:
        shutil.rmtree(image_temp_dir)
        print(f"Removed temporary image directory: {image_temp_dir}")


# ==============================================================================
# --- Script Entry Point ---
# ==============================================================================
if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Generate a storybook PDF from JSON data.")
    # Files
    parser.add_argument("--data-file", required=True, help="Path to the JSON file containing story data.")
    parser.add_argument("--output-file", required=True, help="Path to save the generated PDF file.")
    # Page Layout
    parser.add_argument("--width-mm", required=True, type=float, help="Page trim width in millimeters.")
    parser.add_argument("--height-mm", required=True, type=float, help="Page trim height in millimeters.")
    parser.add_argument("--bleed-mm", default=0.0, type=float, help="Bleed in millimeters for each edge.")
    parser.add_argument("--dpi", required=True, type=int, help="DPI for image processing.")
    parser.add_argument("--show-bleed-marks", action="store_true", help="If set, draw crop marks on the PDF.")
    # START MODIFICATION: Add margin arguments.
    parser.add_argument("--margin-top-mm", required=True, type=float, help="Top margin in millimeters.")
    parser.add_argument("--margin-bottom-mm", required=True, type=float, help="Bottom margin in millimeters.")
    parser.add_argument("--margin-inside-mm", required=True, type=float, help="Inside (gutter) margin in millimeters.")
    parser.add_argument("--margin-outside-mm", required=True, type=float, help="Outside margin in millimeters.")
    # END MODIFICATION
    # Content
    parser.add_argument("--title-page-text", default="", help="Text for the title page.")
    parser.add_argument("--copyright-text", default="", help="Text for the copyright page.")
    parser.add_argument("--introduction-text", default="", help="Text for the introduction page.")
    parser.add_argument("--wallpaper-file", help="Optional path to the wallpaper image for text pages.")
    # Styling
    parser.add_argument("--font-name", required=True, help="Logical name for the font (e.g., 'LoveYaLikeASister').")
    parser.add_argument("--font-file", required=True, help="Path to the .ttf font file.")
    parser.add_argument("--font-size-main", default=14, type=float, help="Font size for main story text.")
    parser.add_argument("--font-size-footer", default=10, type=float, help="Font size for the footer.")
    parser.add_argument("--font-size-title", default=24, type=float, help="Font size for the title page.")
    parser.add_argument("--font-size-copyright", default=8, type=float, help="Font size for the copyright page.")
    parser.add_argument("--font-size-introduction", default=12, type=float, help="Font size for the introduction page.")
    parser.add_argument("--color-main", default="#000000", help="Hex color for main story text.")
    parser.add_argument("--color-footer", default="#808080", help="Hex color for the footer text.")
    parser.add_argument("--color-title", default="#1E1E64", help="Hex color for the title page text.")
    parser.add_argument("--color-copyright", default="#000000", help="Hex color for the copyright page text.")
    parser.add_argument("--color-introduction", default="#000000", help="Hex color for the introduction page text.")

    args = parser.parse_args()

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

    create_storybook_pdf(args, story_data)
