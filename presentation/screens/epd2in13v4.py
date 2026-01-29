import os


from waveshare_epd import epd2in13_V4
from PIL import Image, ImageDraw, ImageFont
from presentation.observer import Observer


class Epd2in13v4(Observer):
    def __init__(self, observable=None, mode="candle", **kwargs):
        super().__init__(observable)
        self.mode = mode
        self.epd = self._init_display()

    def _init_display(self):
        epd = epd2in13_V4.EPD()
        epd.init()
        epd.Clear()
        return epd

    def update(self, data):
        # Create a blank image
        image = Image.new('1', (self.epd.width, self.epd.height), 255)
        draw = ImageDraw.Draw(image)

        # Simple display (for testing)
        font = ImageFont.load_default()
        text = "BTC Screen OK"
        draw.text((10, 10), text, font=font, fill=0)

        # Display image
        self.epd.display(self.epd.getbuffer(image))

    def close(self):
        self.epd.sleep()
