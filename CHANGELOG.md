# Changelog

All notable changes to `hk100/tcp-socket` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- TCP socket server & client (Swoole and pure-PHP ReactPHP drivers).
- WebSocket server & client with a built-in handshake and frame codec.
- MQTT client with automatic subscribe/loop support.
- Auto-registered console commands for Laravel and Hyperf.
- `auto` driver detection between Swoole and ReactPHP.
