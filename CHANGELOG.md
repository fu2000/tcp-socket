# Changelog

All notable changes to `hk100/tcp-socket` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.1] - 2026-11-23

### Fixed
- Hyperf commands crashed with `Type of ...::$name must be ?string` on Hyperf 3.x:
  the redeclared `$name`, `$description` and `$coroutine` properties now keep the
  typed declarations of `Hyperf\Command\Command`.
- Commands now print a clear message when the configured handler class does not exist.

### Changed
- Docs restructured: `README.md` is an overview; step-by-step guides live in
  `docs/laravel.md` and `docs/hyperf.md` (including how to create the Hyperf
  config file, which is not published automatically).

## [1.0.0] - 2026-11-23

### Added
- TCP socket server & client (Swoole and pure-PHP ReactPHP drivers).
- WebSocket server & client with a built-in handshake and frame codec.
- MQTT client with automatic subscribe/loop support.
- Auto-registered console commands for Laravel and Hyperf.
- `auto` driver detection between Swoole and ReactPHP.
