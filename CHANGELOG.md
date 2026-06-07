# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 2.0.0 — 2026-06-07

- Require `yiisoft/db` ^2.0 and `yiisoft/db-migration` ^2.0; drop yiisoft/db 1.x
  support. Consumers must provide a PSR-16 cache implementation (a transitive
  requirement of yiisoft/db 2.0).

## 1.0.0 — 2026-06-05

- Initial release.
