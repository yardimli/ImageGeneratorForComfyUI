# python/storybook-html2pdf.py

import argparse
import base64
import html
import json
import os
import requests
import sys
from pathlib import Path

from weasyprint import HTML, CSS

def download_image_as_data_uri(url, page_num):
    """Downloads an image and returns it as a base64 data URI."""
    if not url:
        print(f"Warning: No image URL for page {page_num}.", file=sys.stderr)
        return None
    try:
        # Some servers block requests without a user-agent
        headers = {'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'}
        response = requests.get(url, timeout=30, headers=headers)
        response.raise_for_status()
        mime_type = response.headers.get('Content-Type', 'image/jpeg')
        encoded_string = base64.b64encode(response.content).decode('utf-8')
        print(f"Successfully downloaded image for page {page_num}.")
        return f"data:{mime_type};base64,{encoded_string}"
    except requests.exceptions.RequestException as e:
        print(f"Error downloading image for page {page_num} from {url}: {e}", file=sys.stderr)
        return None
    except Exception as e:
        print(f"Error processing image for page {page_num} from {url}: {e}", file=sys.stderr)
        return None

def generate_css(args):
    """Generates a CSS string from the script's arguments."""
    font_file_uri = Path(args.font_file).as_uri()
    wallpaper_uri = Path(args.wallpaper_file).as_uri() if args.wallpaper_file and os.path.exists(args.wallpaper_file) else None

    # Note: We use white-space: pre-wrap to respect newlines from the web form's textareas.
    return f"""
    /* --- Base Setup & Font Configuration --- */
    @font-face {{
        font-family: '{args.font_name}';
        src: url("{font_file_uri}");
    }}

    @page {{
        size: {args.width_mm}mm {args.height_mm}mm;
        margin: 0;
        bleed: {args.bleed_mm}mm;
        {'marks: crop;' if args.show_bleed_marks else ''}
    }}

    /* Named page for story content that needs a page number */
    @page main-content {{
        @bottom-center {{
            content: counter(page);
            font-family: '{args.font_name}';
            font-size: {args.font_size_footer}pt;
            color: {args.color_footer};
            margin-bottom: {args.page_number_margin_bottom_mm}mm;
            vertical-align: top;
        }}
    }}

    body {{
        font-family: '{args.font_name}', sans-serif;
        margin: 0;
        padding: 0;
    }}

    /* --- Page Structure & Layout --- */
    .page {{
        width: {args.width_mm}mm;
        height: {args.height_mm}mm;
        position: relative;
        overflow: hidden;
        box-sizing: border-box;
        page-break-after: always;
        display: flex;
        flex-direction: column;
    }}

    .page:last-child {{
        page-break-after: auto;
    }}

    .content-box {{
        width: 100%;
        box-sizing: border-box;
        white-space: pre-wrap;
        word-wrap: break-word;
    }}

    /* Vertical Alignment Helpers */
    .valign-top {{ justify-content: flex-start; }}
    .valign-middle {{ justify-content: center; }}
    .valign-bottom {{ justify-content: flex-end; }}

    /* --- Special Pages (Title, Copyright, Intro) --- */
    .title-page, .copyright-page, .introduction-page {{
        padding: {args.bleed_mm}mm;
    }}

    .title-page .content-box {{
        color: {args.color_title};
        font-size: {args.font_size_title}pt;
        text-align: center;
        padding: 0 {args.margin_horizontal_title_mm}mm;
    }}

    .copyright-page .content-box {{
        color: {args.color_copyright};
        font-size: {args.font_size_copyright}pt;
        text-align: center;
        padding: 0 {args.margin_horizontal_copyright_mm}mm;
    }}

    .introduction-page .content-box {{
        color: {args.color_introduction};
        font-size: {args.font_size_introduction}pt;
        text-align: justify;
        padding: 0 {args.margin_horizontal_introduction_mm}mm;
    }}

    /* --- Story Content Pages --- */
    .story-image-page img {{
        width: calc({args.width_mm}mm + 2 * {args.bleed_mm}mm);
        height: calc({args.height_mm}mm + 2 * {args.bleed_mm}mm);
        position: absolute;
        top: -{args.bleed_mm}mm;
        left: -{args.bleed_mm}mm;
        object-fit: cover;
    }}

    .story-text-page {{
        page: main-content; /* Apply the page style with the footer */
        padding: {args.bleed_mm}mm;
        display: flex;
        justify-content: center;
        align-items: center;
        text-align: center;
        background-size: cover;
        background-position: center;
        {f'background-image: url("{wallpaper_uri}");' if wallpaper_uri else ''}
    }}

    .story-text-page .text-container {{
        color: {args.color_main};
        font-size: {args.font_size_main}pt;
        width: calc({args.width_mm}mm - 2 * {args.margin_horizontal_main_mm}mm);
        height: calc({args.height_mm}mm - 2 * {args.margin_horizontal_main_mm}mm);
        border: 1px dotted #999;
        border-radius: 10mm;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 10mm;
        box-sizing: border-box;
    }}
    """

def generate_html(args, story_data, image_uris):
    """Generates the full HTML document string."""
    html_parts = []

    # --- Special Pages ---
    if args.title_page_text:
        html_parts.append(f"""
        <div class="page title-page valign-{args.valign_title}">
            <div class="content-box">{html.escape(args.title_page_text)}</div>
        </div>""")

    if args.copyright_text:
        html_parts.append(f"""
        <div class="page copyright-page valign-{args.valign_copyright}">
            <div class="content-box">{html.escape(args.copyright_text)}</div>
        </div>""")

    if args.introduction_text:
        html_parts.append(f"""
        <div class="page introduction-page valign-{args.valign_introduction}">
            <div class="content-box">{html.escape(args.introduction_text)}</div>
        </div>""")

    # --- Story Pages (Text followed by Image) ---
    for i, page_data in enumerate(story_data.get("pages", [])):
        text = page_data.get("text", "")
        image_uri = image_uris[i]

        # Text Page
        html_parts.append(f"""
        <div class="page story-text-page">
            <div class="text-container">
                <div class="content-box">{html.escape(text)}</div>
            </div>
        </div>""")

        # Image Page
        html_parts.append(f"""
        <div class="page story-image-page">
            {'<img src="' + image_uri + '">' if image_uri else '<p>Image could not be loaded.</p>'}
        </div>""")

    body_content = "\n".join(html_parts)
    return f"<!DOCTYPE html><html><head><meta charset='UTF-8'><style>{generate_css(args)}</style></head><body>{body_content}</body></html>"


def main():
    """Main execution function."""
    parser = argparse.ArgumentParser(description="Generate a storybook PDF from JSON data using HTML and CSS.")
    # Files
    parser.add_argument("--data-file", required=True, help="Path to the JSON file containing story data.")
    parser.add_argument("--output-file", required=True, help="Path to save the generated PDF file.")
    # Page Layout
    parser.add_argument("--width-mm", required=True, type=float, help="Page trim width in millimeters.")
    parser.add_argument("--height-mm", required=True, type=float, help="Page trim height in millimeters.")
    parser.add_argument("--bleed-mm", default=0.0, type=float, help="Bleed in millimeters for each outer edge.")
    parser.add_argument("--dpi", required=True, type=int, help="DPI for image processing (Note: Not directly used by WeasyPrint, but kept for interface compatibility).")
    parser.add_argument("--show-bleed-marks", action="store_true", help="If set, draw crop marks on the PDF.")
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
    # Margin and Alignment Arguments
    parser.add_argument("--valign-title", choices=['top', 'middle', 'bottom'], default='middle', help="Vertical alignment for the title page.")
    parser.add_argument("--margin-horizontal-title-mm", type=float, default=25.4, help="Horizontal margin for the title page in mm.")
    parser.add_argument("--valign-copyright", choices=['top', 'middle', 'bottom'], default='bottom', help="Vertical alignment for the copyright page.")
    parser.add_argument("--margin-horizontal-copyright-mm", type=float, default=25.4, help="Horizontal margin for the copyright page in mm.")
    parser.add_argument("--valign-introduction", choices=['top', 'middle', 'bottom'], default='top', help="Vertical alignment for the introduction page.")
    parser.add_argument("--margin-horizontal-introduction-mm", type=float, default=25.4, help="Horizontal margin for the introduction page in mm.")
    parser.add_argument("--margin-horizontal-main-mm", type=float, default=19, help="Horizontal margin for main story text pages in mm.")
    parser.add_argument("--page-number-margin-bottom-mm", type=float, default=12.7, help="Bottom margin for page numbers in mm.")

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

    # 2. Download all images and convert to data URIs
    print("Downloading images...")
    image_uris = [download_image_as_data_uri(p.get("image_url"), i + 1) for i, p in enumerate(story_data["pages"])]

    # 3. Generate the full HTML content with embedded CSS
    print("Generating HTML and CSS...")
    full_html = generate_html(args, story_data, image_uris)

    # 4. Render the PDF using WeasyPrint
    print("Rendering PDF with WeasyPrint...")
    html_doc = HTML(string=full_html)

    html_doc.write_pdf(args.output_file)

    print(f"\nPDF successfully created: {args.output_file}")

if __name__ == "__main__":
    main()
