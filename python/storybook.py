import json
import os
from PIL import Image
from fpdf import FPDF

# ==============================================================================
# --- CONFIGURATION ---
# ==============================================================================
CONFIG = {
    # --- Page & Document Settings ---
    "PAGE_WIDTH_MM": 150,
    "PAGE_HEIGHT_MM": 150,
    "PDF_DPI": 300,  # DPI for image processing within the PDF

    # --- Content Settings ---
    "BOOK_TITLE": "Les Misérables",
    "BOOK_SUBTITLE": "A simplified storybook",
    
    # --- Font Settings ---
    "FONT_NAME": "CactusClassicalSerif",
    "FONT_TTF_FILE": "CactusClassicalSerif-Regular.ttf",

    # --- File & Folder Paths ---
    "SOURCE_FOLDER": "./les-misrables-ch",  # Folder containing JSON, images, font, and wallpaper
    "JSON_FILENAME": "storybook.json",
    "WALLPAPER_FILENAME": "wallpaper3.jpg",
    "OUTPUT_PDF_FILENAME": "les-misrables-ch.pdf"
}
# ==============================================================================


def convert_png_to_jpg(folder, page_count):
    """Converts PNG images (1.png, 2.png, etc.) to JPG format."""
    print(f"--- Converting {page_count} PNGs to JPGs ---")
    for i in range(1, page_count + 1):
        png_path = os.path.join(folder, f"{i}.png")
        jpg_path = os.path.join(folder, f"{i}.jpg")
        if os.path.exists(png_path):
            with Image.open(png_path) as im:
                # Convert if it has transparency or is palette-based
                if im.mode in ("RGBA", "P"):
                    im = im.convert("RGB")
                im.save(jpg_path, 'JPEG', quality=95)
                print(f"Converted: {png_path} -> {jpg_path}")
        else:
            # This is not an error if a JPG already exists
            if not os.path.exists(jpg_path):
                print(f"Warning: Image not found for page {i}: {png_path}")
    print("--- Conversion complete ---")


class StorybookPDF(FPDF):
    """
    Custom PDF class with configurable page size, rounded borders, and footers.
    The DPI setting influences how images are processed and embedded.
    """
    def __init__(self, width, height, dpi):
        super().__init__(orientation='P', unit='mm', format=(width, height))
        # The dpi parameter is used by fpdf2 for image processing
        self.dpi = dpi
        self.set_auto_page_break(auto=True, margin=15)
        self.show_footer = False
        self.logical_page_number = 0

    def footer(self):
        if self.show_footer:
            self.set_y(-20)
            self.set_font(CONFIG["FONT_NAME"], 'I', 10)
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


def create_storybook_pdf(config, text_pages):
    """Generates the complete storybook PDF from configuration and text data."""
    
    pdf = StorybookPDF(
        width=config["PAGE_WIDTH_MM"],
        height=config["PAGE_HEIGHT_MM"],
        dpi=config["PDF_DPI"]
    )
    
    # --- Font Setup ---
    font_path = config["FONT_TTF_FILE"]
    try:
        pdf.add_font(config["FONT_NAME"], '', font_path)
        pdf.add_font(config["FONT_NAME"], 'I', font_path) # Italic style
    except RuntimeError as e:
        print(f"WARNING: Font '{config['FONT_TTF_FILE']}' not found or failed to load. Falling back to Arial.")
        print(f"Error details: {e}")
        # Use a fallback font name that fpdf2 knows
        config["FONT_NAME"] = "Arial"


    # --- Title Page ---
    pdf.add_page()
    pdf.set_font(config["FONT_NAME"], '', 24)
    pdf.set_text_color(30, 30, 100)
    pdf.set_y(pdf.h / 2 - 20) # Center vertically
    pdf.cell(0, 10, config["BOOK_TITLE"], align='C')
    pdf.ln(15)
    pdf.set_font(config["FONT_NAME"], '', 14)
    pdf.set_text_color(0)
    pdf.cell(0, 10, config["BOOK_SUBTITLE"], align='C')
    pdf.show_footer = False

    # --- Text and Image Pages ---
    for i, text in enumerate(text_pages, start=1):
        # --- TEXT PAGE ---
        pdf.add_page()
        pdf.logical_page_number += 1
        
        wallpaper_path = config["WALLPAPER_FILENAME"]
        if os.path.exists(wallpaper_path):
            pdf.image(wallpaper_path, x=0, y=0, w=pdf.w, h=pdf.h)
            
        pdf.draw_rounded_dotted_border(margin=10, radius=10)
        pdf.set_font(config["FONT_NAME"], '', 14)
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
        image_path = os.path.join(config["SOURCE_FOLDER"], f"{i}.jpg")
        if os.path.exists(image_path):
            pdf.image(image_path, x=0, y=0, w=pdf.w, h=pdf.h)
        else:
            pdf.set_font(config["FONT_NAME"], '', 14)
            pdf.set_xy(0, pdf.h / 2 - 5)
            pdf.cell(0, 10, f"Image not found: {i}.jpg", align='C')
        pdf.show_footer = False

    output_path = os.path.join(config["SOURCE_FOLDER"], config["OUTPUT_PDF_FILENAME"])
    pdf.output(output_path)
    print(f"\nPDF successfully created: {output_path}")


if __name__ == "__main__":
    # 1. Load story data to determine page count
    json_path = os.path.join(CONFIG["SOURCE_FOLDER"], CONFIG["JSON_FILENAME"])
    try:
        with open(json_path, "r", encoding="utf-8") as f:
            story_pages = json.load(f)
    except FileNotFoundError:
        print(f"FATAL ERROR: JSON file not found at '{json_path}'")
        exit()
    except json.JSONDecodeError:
        print(f"FATAL ERROR: Could not parse the JSON file '{json_path}'. Check for syntax errors.")
        exit()

    page_count = len(story_pages)
    if page_count == 0:
        print("Warning: JSON file is empty. No pages to generate.")
        exit()

    # 2. Convert necessary images from PNG to JPG
    convert_png_to_jpg(CONFIG["SOURCE_FOLDER"], page_count)
    
    # 3. Generate the PDF
    create_storybook_pdf(CONFIG, story_pages)