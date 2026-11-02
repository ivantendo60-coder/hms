#!/usr/bin/env python3
# Simple bridge: reads "UID:xxxx" lines from Arduino serial and POSTs to PHP endpoint.
# Usage: python3 serial_bridge.py [--port COM3] [--check]
import argparse
import json
import sys
import urllib.parse
import urllib.request
import urllib.error

import serial
from serial.tools import list_ports

URL = 'http://localhost/hms/api/rfid_ingest.php'
STATION = 'reception'

KNOWN_BOARD_KEYWORDS = [
    'arduino', 'uno', 'mega', 'nano', 'esp32', 'esp8266',
    'ch340', 'cp210', 'ft232', 'usb serial', 'usb2serial', 'silicon labs', 'wch',
]


def find_serial_port(preferred=None):
    ports = list_ports.comports()
    if not ports:
        return None

    if preferred:
        preferred_name = str(preferred).lower()
        for port in ports:
            if preferred_name in (port.device or '').lower():
                return port.device

    candidates = []
    for port in ports:
        desc = (port.description or '').lower()
        hwid = (port.hwid or '').lower()
        if any(keyword in desc for keyword in KNOWN_BOARD_KEYWORDS) or any(keyword in hwid for keyword in KNOWN_BOARD_KEYWORDS):
            candidates.append(port.device)

    if len(candidates) == 1:
        return candidates[0]
    if candidates:
        return candidates[0]

    if len(ports) == 1:
        return ports[0].device

    return None


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--port', dest='port')
    parser.add_argument('--check', action='store_true')
    args = parser.parse_args()

    port = args.port or find_serial_port(args.port)
    if args.check:
        if not port:
            print(json.dumps({'ok': False, 'message': 'No board port detected.', 'device_connected': False}))
            return 1
        print(json.dumps({
            'ok': True,
            'port': port,
            'message': 'Reader port detected on ' + port + '. Scan a card to confirm it is responding.',
            'device_detected': True,
            'device_connected': False,
        }))
        return 0

    if not port:
        available = [p.device for p in list_ports.comports()]
        print('No auto-detected board port found.')
        if available:
            print('Available serial ports:')
            for p in available:
                print('  -', p)
            print('\nPlease specify the correct port as an argument, e.g. python3 serial_bridge.py --port COM3')
        else:
            print('No serial ports were detected on this system.')
        return 1

    try:
        with serial.Serial(port, 9600, timeout=2) as s:
            print(f"Listening on {port} -> {URL}")
            while True:
                line = s.readline().decode(errors='ignore').strip()
                if not line:
                    continue
                print('SERIAL:', line)
                if line.startswith('UID:'):
                    uid = line[4:].strip()
                    if not uid:
                        print('Skipped empty UID from serial line')
                        continue
                    payloads = [
                        ('form', urllib.parse.urlencode({'uid': uid, 'station': STATION}).encode('utf-8')),
                        ('json', json.dumps({'uid': uid, 'station': STATION}).encode('utf-8')),
                    ]
                    for kind, body in payloads:
                        try:
                            headers = {'Content-Type': 'application/x-www-form-urlencoded'} if kind == 'form' else {'Content-Type': 'application/json'}
                            req = urllib.request.Request(URL, data=body, headers=headers, method='POST')
                            with urllib.request.urlopen(req, timeout=3) as response:
                                print('POST', kind, uid, response.status, response.read().decode('utf-8', 'ignore'))
                        except urllib.error.HTTPError as err:
                            print('HTTP', kind, uid, err.code, err.read().decode('utf-8', 'ignore'))
                        except Exception as e:
                            print('ERR', kind, uid, e)
    except KeyboardInterrupt:
        print('Bridge stopped.')
        return 0
    except Exception as exc:
        print('Bridge error:', exc)
        return 1


if __name__ == '__main__':
    sys.exit(main())
