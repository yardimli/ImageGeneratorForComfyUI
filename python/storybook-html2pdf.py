import argparse
import base64
import html
import json
import mimetypes # MODIFICATION: Import mimetypes
import os
import requests
import sys
from pathlib import Path

from weasyprint import HTML, CSS

# START MODIFICATION: Add helper to convert local files to data URI
def file_to_data_uri(filepath):
    """Reads a local file and returns it as a base64 data URI."""
    if not filepath or not os.path.exists(filepath):
        return None
    try:
        mime_type, _ = mimetypes.guess_type(filepath)
        if not mime_type:
            mime_type = 'application/octet-stream' # Fallback
        with open(filepath, "rb") as f:
            encoded_string = base64.b64encode(f.read()).decode('utf-8')
        return f"data:{mime_type};base64,{encoded_string}"
    except Exception as e:
        print(f"Error converting file to data URI: {filepath}, {e}", file=sys.stderr)
        return None
# END MODIFICATION

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
    font_faces = []
    font_types = ['main', 'title', 'copyright', 'introduction']
    unique_fonts = {
        (getattr(args, f"font_name_{type}"), getattr(args, f"font_file_{type}"))
        for type in font_types
    }
    for font_name, font_file in unique_fonts:
        font_file_uri = Path(font_file).as_uri()
        font_faces.append(f"""
    @font-face {{
        font-family: '{font_name}';
        src: url("{font_file_uri}");
    }}""")
    font_face_css = "\n".join(font_faces)

    wallpaper_uri = Path(args.wallpaper_file).as_uri() if args.wallpaper_file and os.path.exists(args.wallpaper_file) else None

    bleed_width_mm = args.width_mm + (2 * args.bleed_mm)
    bleed_height_mm = args.height_mm + (2 * args.bleed_mm)

    if args.enable_dashed_border:
        dashed_border_style = f"border: {args.dashed_border_width}px dashed {args.dashed_border_color};"
    else:
        dashed_border_style = "border: none;"

    return f"""
    /* --- Base Setup & Font Configuration --- */
    {font_face_css}

    @page {{
        size: {bleed_width_mm}mm {bleed_height_mm}mm;
        margin: 0;
    }}

    body {{
        font-family: '{args.font_name_main}', sans-serif;
        margin: 0;
        padding: 0;
        counter-reset: page;
    }}

    /* --- Page Structure & Layout --- */
    .page {{
        width: {bleed_width_mm}mm;
        height: {bleed_height_mm}mm;
        position: relative;
        overflow: hidden;
        box-sizing: border-box;
        page-break-after: always;
        display: flex;
        flex-direction: column;
        counter-increment: page;
    }}

    .page:last-child {{
        page-break-after: auto;
    }}

    .page-number {{
        position: absolute;
        bottom: calc({args.bleed_mm}mm + {args.page_number_margin_bottom_mm}mm);
        left: 0;
        right: 0;
        text-align: center;
    }}
    .page-number::after {{
        content: counter(page);
        font-family: '{args.font_name_main}';
        font-size: {args.font_size_footer}pt;
        color: {args.color_footer};
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

    /* START MODIFICATION: New Title Page Styling */
    .title-page-container {{
        justify-content: center;
        align-items: center;
        background-size: cover;
        background-position: center;
        padding: {args.bleed_mm}mm;
    }}

    .title-page-frame {{
        width: 100%;
        height: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 5%;
        box-sizing: border-box;
    }}

    .title-page-content {{
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: center;
        text-align: center;
        font-family: '{args.font_name_title}';
    }}

    .title-page-header {{
        /* container for logo and top text */
    }}

    .title-logo {{
        max-height: 10vh;
        max-width: 30%;
        margin-top: 1em;
    }}

    .title-stickers-container {{
        width: 100%;
        text-align: center;
        margin-top: 1em;
    }}

    .title-sticker-img {{
        max-height: 75px;
        max-width: 75px;
        object-fit: contain;
    }}

    .title-top-text {{
        font-size: {args.font_size_title * 0.8}pt;
        color: {args.color_title};
        opacity: 0.9;
        margin-bottom: 1em;
        margin-top:4em;
    }}

    .title-main-text {{
        font-size: {args.font_size_title * 2.0}pt;
        color: {args.color_title};
        font-weight: bold;
        line-height: 1.1;
        margin: 0.2em 0;
    }}

    .title-author-text {{
        font-family: '{args.font_name_main}';
        font-size: {args.font_size_title * 0.9}pt;
        color: {args.color_main};
        margin-top: 0.5em;
    }}

    .title-page-footer {{
        /* container for bottom text */
    }}

    .title-bottom-text {{
        font-family: '{args.font_name_main}';
        font-size: {args.font_size_footer}pt;
        color: {args.color_footer};
    }}
    /* END MODIFICATION */

    /* --- Special Pages (Copyright, Intro) --- */
    .copyright-page, .introduction-page {{
        padding: {args.bleed_mm}mm;
        margin-top: 5em;
        margin-bottom: 5em;
    }}

    .copyright-page .content-box {{
        color: {args.color_copyright};
        font-family: '{args.font_name_copyright}';
        font-size: {args.font_size_copyright}pt;
        line-height: {args.line_height_copyright};
        text-align: center;
        padding: 0 {args.margin_horizontal_copyright_mm}mm;
        margin-top: 5em;
        margin-bottom: 5em;
    }}

    .introduction-page .content-box {{
        color: {args.color_introduction};
        font-family: '{args.font_name_introduction}';
        font-size: {args.font_size_introduction}pt;
        line-height: {args.line_height_introduction};
        text-align: justify;
        padding: 0 {args.margin_horizontal_introduction_mm}mm;
        margin-top: 5em;
        margin-bottom: 5em;
    }}

    /* --- Story Content Pages --- */
    .story-image-page {{
        padding: 0;
    }}

    .story-image-page img {{
        width: 100%;
        height: 100%;
        object-fit: cover;
    }}

    .story-text-page {{
        padding: {args.bleed_mm}mm;
        display: flex;
        justify-content: center;
        align-items: center;
        background-size: cover;
        background-position: center;
        {f'background-image: url("{wallpaper_uri}");' if wallpaper_uri else ''}
    }}

    .story-text-page .text-background {{
        background-color: {args.text_background_color};
        border-radius: 25px;
        padding: 10px;
        width: {args.text_box_width}%;
        height: {args.text_box_width}%;
        box-sizing: border-box;
        display: flex;
        justify-content: center;
        align-items: center;
    }}

    .story-text-page .text-container {{
        color: {args.color_main};
        font-family: '{args.font_name_main}';
        font-size: {args.font_size_main}pt;
        line-height: {args.line_height_main};
        background-color: {args.text_background_color};
        width: 100%;
        height: 100%;
        {dashed_border_style}
        border-radius: 25px;
        display: flex;
        justify-content: center;
        align-items: center;
        box-sizing: border-box;
        padding: 20px;
    }}

    .content-box {{
        width: 100%;
        box-sizing: border-box;
        white-space: pre-wrap;
        word-wrap: break-word;
        text-align: center;
    }}
    """

def generate_html(args, story_data, image_uris):
    """Generates the full HTML document string."""
    html_parts = []

    # START MODIFICATION: Generate new title page
    title_wallpaper_uri = file_to_data_uri(args.title_wallpaper_file)
    title_logo_uri = file_to_data_uri(args.title_logo_file)
    sticker_uris = [file_to_data_uri(f) for f in args.sticker_file if f] # MODIFICATION: Convert sticker files to data URIs

    # Only create a title page if there's some content for it
    if any([args.title_top_text, args.title_main_text, args.title_author_text, args.title_bottom_text, title_logo_uri]):
        title_page_html = f"""
        <div class="page title-page-container" style="background-image: url('{title_wallpaper_uri or ''}');">
            <div class="title-page-frame">
                <div class="title-page-content">
                    <div class="title-page-header">
                        {f'<div class="title-top-text">{html.escape(args.title_top_text)}</div>' if args.title_top_text else ''}
                    </div>
                    <div class="title-page-body">
                        {f'<div class="title-main-text">{html.escape(args.title_main_text)}</div>' if args.title_main_text else ''}
                        {f'<div class="title-author-text">{html.escape(args.title_author_text)}</div>' if args.title_author_text else ''}
                    </div>
                    <div class="title-page-footer">
                        {f'<div class="title-bottom-text">{html.escape(args.title_bottom_text)}</div>' if args.title_bottom_text else ''}

                        { # MODIFICATION: Add sticker container and images
                          '<div class="title-stickers-container">' +
                          ''.join([f'<img src="{uri}" class="title-sticker-img">' for uri in sticker_uris]) +
                          '</div>' if sticker_uris else ''
                        }

                        {f'<img src="{title_logo_uri}" class="title-logo">' if title_logo_uri else ''}
                    </div>
                </div>
            </div>
        </div>
        """
        html_parts.append(title_page_html)
    # END MODIFICATION

    # --- Copyright & Intro Pages ---
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

        html_parts.append(f"""
            <div class="page story-text-page">
                <div class="text-background">
                    <div class="text-container">
                        <div class="content-box">{html.escape(text)}</div>
                    </div>
                </div>
                <div class="page-number"></div>
            </div>""")

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
    # START MODIFICATION: Add new title page arguments
    parser.add_argument("--title-wallpaper-file", help="Optional path to the wallpaper image for the title page.")
    parser.add_argument("--title-logo-file", help="Optional path to the logo image for the title page.")
    parser.add_argument("--sticker-file", action="append", default=[], help="Path to a sticker file to be placed on the title page.") # MODIFICATION: Add sticker argument
    parser.add_argument("--title-top-text", default="", help="Text for the top of the title page.")
    parser.add_argument("--title-main-text", default="", help="Main title text.")
    parser.add_argument("--title-author-text", default="", help="Author text for the title page.")
    parser.add_argument("--title-bottom-text", default="", help="Text for the bottom of the title page.")
    # END MODIFICATION
    parser.add_argument("--copyright-text", default="", help="Text for the copyright page.")
    parser.add_argument("--introduction-text", default="", help="Text for the introduction page.")
    parser.add_argument("--wallpaper-file", help="Optional path to the wallpaper image for text pages.")

    font_types = ['main', 'title', 'copyright', 'introduction']
    for type in font_types:
        parser.add_argument(f"--font-name-{type}", required=True, help=f"Logical name for the {type} font.")
        parser.add_argument(f"--font-file-{type}", required=True, help=f"Path to the .ttf font file for {type}.")
        parser.add_argument(f"--line-height-{type}", required=True, type=float, help=f"Line height for {type} text.")

    # Styling
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
    # START MODIFICATION: Removed title page valign/margin args
    # END MODIFICATION
    parser.add_argument("--valign-copyright", choices=['top', 'middle', 'bottom'], default='bottom', help="Vertical alignment for the copyright page.")
    parser.add_argument("--margin-horizontal-copyright-mm", type=float, default=25.4, help="Horizontal margin for the copyright page in mm.")
    parser.add_argument("--valign-introduction", choices=['top', 'middle', 'bottom'], default='top', help="Vertical alignment for the introduction page.")
    parser.add_argument("--margin-horizontal-introduction-mm", type=float, default=25.4, help="Horizontal margin for the introduction page in mm.")
    parser.add_argument("--page-number-margin-bottom-mm", type=float, default=12.7, help="Bottom margin for page numbers in mm.")
    # Text Page Styling
    parser.add_argument("--text-box-width", type=float, default=80, help="Width of the text box as a percentage.")
    parser.add_argument("--text-background-color", default="transparent", help="Background color for the text box (hex or 'transparent').")

    parser.add_argument("--enable-dashed-border", action="store_true", help="If set, enables the dashed border on text pages.")
    parser.add_argument("--dashed-border-width", type=float, default=5, help="Width of the dashed border in points.")
    parser.add_argument("--dashed-border-color", default="#333333", help="Color of the dashed border.")

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
