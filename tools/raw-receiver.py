#!/usr/bin/env python3
"""TAMEERO CI A/B control receiver (v2.8.14) - evidence only.
Listens on 127.0.0.1:PORT, reads ONE raw POST body, counts bytes,
hashes SHA-256, DISCARDS everything (nothing is written to disk,
so disk speed is not a variable). Prints one RECEIVED line, then
exits. Used by the Windows smoke test to compare the PHP built-in
server's receive path against a plain TCP receiver on the same
runner, same file, same client. Stdlib only.
"""
import argparse
import hashlib
import socket
import sys
import time


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--port", type=int, required=True)
    ap.add_argument("--max-seconds", type=int, default=300)
    args = ap.parse_args()
    srv = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    srv.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    srv.bind(("127.0.0.1", args.port))
    srv.listen(1)
    srv.settimeout(args.max_seconds + 60)
    print("RECEIVER-START port:%d" % args.port, flush=True)
    conn, _addr = srv.accept()
    conn.settimeout(args.max_seconds + 60)
    buf = b""
    while b"\r\n\r\n" not in buf:
        data = conn.recv(65536)
        if not data:
            break
        buf += data
    head, _sep, rest = buf.partition(b"\r\n\r\n")
    length = 0
    for line in head.split(b"\r\n"):
        if line.lower().startswith(b"content-length:"):
            length = int(line.split(b":", 1)[1].strip())
    h = hashlib.sha256()
    got = len(rest)
    h.update(rest)
    t0 = time.time()
    while got < length:
        data = conn.recv(1024 * 1024)
        if not data:
            break
        got += len(data)
        h.update(data)
    dt = time.time() - t0
    try:
        conn.sendall(
            b"HTTP/1.1 200 OK\r\n"
            b"Content-Type: text/plain\r\n"
            b"Content-Length: 2\r\n"
            b"Connection: close\r\n\r\nok"
        )
    except OSError:
        pass
    conn.close()
    srv.close()
    print(
        "RECEIVED bytes:%d expected:%d sha256:%s seconds:%.2f"
        % (got, length, h.hexdigest(), dt),
        flush=True,
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
