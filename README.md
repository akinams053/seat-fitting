# seat-fitting

[![Latest Stable Version](https://img.shields.io/packagist/v/akinams053/seat-fitting.svg?style=flat-square)]()
[![License](https://img.shields.io/badge/license-GPLv2-blue.svg?style=flat-square)](LICENSE)

A module for [SeAT](https://github.com/eveseat/seat) that holds fittings and can compare the required skills for a fit to your character.

> **⚠️ 二次开发分支 · Downstream fork**
>
> 本仓库基于上游 [`cryptatech/seat-fitting`](https://github.com/eveseat-plugins/seat-fitting) fork，并按本项目需求做了定制（移除价格集成与 About 页、加入中文翻译等）。需要官方未改动版本，请使用原仓库。
>
> Forked from upstream [`cryptatech/seat-fitting`](https://github.com/eveseat-plugins/seat-fitting) with downstream customizations. For the canonical plugin, please use the original repository instead.

## Quick Installation

In your SeAT directory (default `/var/www/seat`):

```
php artisan down
composer require akinams053/seat-fitting

php artisan vendor:publish --force --all
php artisan migrate

php artisan up
```

If using a docker installation see https://eveseat.github.io/docs/admin_guides/docker_admin/#installing-plugins

Use the package name `akinams053/seat-fitting`. After install, a **Fittings** link will appear in the SeAT left sidebar.

## Credits · 致谢

This fork stands entirely on the work of the upstream authors:

- **Denngarr B'tarn** — original author of the plugin
- **Crypta Electrica** — long-time upstream maintainer of [`eveseat-plugins/seat-fitting`](https://github.com/eveseat-plugins/seat-fitting)

本 fork 完全建立在上游作者的成果之上，特此致敬。Upstream 仍是该插件的权威来源；与本 fork 自身定制无关的 bug 报告、功能请求请向 [`eveseat-plugins/seat-fitting`](https://github.com/eveseat-plugins/seat-fitting) 提出。

Licensed under GPL-2.0-only (see [LICENSE](LICENSE)).
