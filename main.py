
import socket

def wait_for_internet():
    while True:
        try:
            socket.gethostbyname("api.exchange.coinbase.com")
            return
        except:
            logger.info("Waiting for internet...")
            time.sleep(5)

wait_for_internet()


import json
import time
import requests
import urllib.parse
from datetime import datetime, timezone, timedelta
from urllib.error import HTTPError, URLError

from config.builder import Builder
from config.config import config
from logs import logger
from presentation.observer import Observable

DATA_SLICE_DAYS = 1
DATETIME_FORMAT = "%Y-%m-%dT%H:%M"


def get_dummy_data():
    # TODO: Implement functionality to provide dummy data for testing purposes.
    return []


def fetch_prices():
    logger.info('Fetching prices')

    timeslot_end = datetime.now(timezone.utc)
    end_date = timeslot_end.strftime(DATETIME_FORMAT)
    start_date = (timeslot_end - timedelta(days=DATA_SLICE_DAYS)).strftime(DATETIME_FORMAT)

    url = (
        f'https://api.exchange.coinbase.com/products/{config.currency}/candles?'
        f'granularity=900&start={urllib.parse.quote_plus(start_date)}&end={urllib.parse.quote_plus(end_date)}'
    )

    headers = {"Accept": "application/json"}

    try:
        response = requests.get(url, headers=headers, timeout=5)
        response.raise_for_status()  # raises HTTPError if status != 200

        external_data = response.json()
        prices = [entry[1:5] for entry in external_data[::-1]]
        return prices

    except Exception as e:
        logger.error(f"Coinbase API error: {e}")
        return []  # IMPORTANT: never block or crash



def main():
    logger.info('Initialize')

    data_sink = Observable()
    builder = Builder(config)
    builder.bind(data_sink)

    try:
        while True:
            try:
                if config.dummy_data:
                    prices = [entry[1:] for entry in get_dummy_data()]
                else:
                    prices = fetch_prices()

                if prices:
                    data_sink.update_observers(prices)
                else:
                    logger.warning("No price data received")

                time.sleep(config.refresh_interval)

            except Exception as e:
                logger.error(f"Loop error: {e}")
                time.sleep(5)

    except KeyboardInterrupt:
        logger.info('Exit')
        data_sink.close()
        exit()


if __name__ == "__main__":
    main()
