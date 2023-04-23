<!--- BEGIN HEADER -->
# Changelog

All notable changes to this project will be documented in this file.
<!--- END HEADER -->

## [3.3.0](https://github.com/linc/nitro-porter/compare/v3.2.0...v3.3.0) (2023-04-23)

### Features

* Add support for IPB4. ([28f69b](28f69bd17b0cab9b18eb753f9107e934eb1ae7d1))
* Allow run without config.php and give notice. ([70e268](https://github.com/linc/nitro-porter/commit/70e268bb4fd58df4752e5616970326aac088aa4e))
* Condense global src/target logging format. ([b9b053](https://github.com/linc/nitro-porter/commit/b9b0535ce544e0c8f680ecd21a27c859fd55dff0))
* Make 'no permission' warning global. ([0d5f56](https://github.com/linc/nitro-porter/commit/0d5f5668dd419575e904cd1f8f684d390e0e222a))
* Add a bunch of logging to core. ([a4003d](https://github.com/linc/nitro-porter/commit/a4003da2e02c0178b195a701fbc12bf5346648fa))
* Add clarity and data checks to Flarum target. ([86e05e](https://github.com/linc/nitro-porter/commit/86e05eefd1da7ab2642bd6fd4df4bc6a0c49e80f))

### Bug Fixes

* Fix limit for Xenforo PM subject lines. ([ab9b03](https://github.com/linc/nitro-porter/commit/ab9b0312cdbdc883c22814a7c9ac6dcd51dc5019))
* Enforce primary key on Flarum discussions.id (fixes post numbering performance). ([bf20d1](https://github.com/linc/nitro-porter/commit/bf20d1c99f4fec08ac4a61d9f4fb23190564cd87))
* Fix Flarum Postscript for migrations without discussion read records. ([f37b0f](https://github.com/linc/nitro-porter/commit/f37b0f85bc4b7f09e84976eb7d31a1fb9da3ce6f))
* Fix ASP comment body transfer. ([9fda19](https://github.com/linc/nitro-porter/commit/9fda192fcb4005fcdbaedd9b8f54e945d0c540b9)) and cleanup formatting. ([0953ac](https://github.com/linc/nitro-porter/commit/0953acd737ebf76b0dbbdc4aa5136fc8e3b9ffbe))
* Modify Flarum target to always output role tables. ([73c717](https://github.com/linc/nitro-porter/commit/73c717b1d63ccaaeffcf52a00fc7df0d359f3c1d))
* Gate MySQL-only optimizations. ([6a037a](https://github.com/linc/nitro-porter/commit/6a037a0a430ace0d18ce322945a1f986dd07d140))
* Improve old db exists() function to do what you think it does. ([198ae7](https://github.com/linc/nitro-porter/commit/198ae728e6d7b012cb7872e0279515b20b01134a))
* Reach PHPStan level 5 by fixing param typing. ([b81182](https://github.com/linc/nitro-porter/commit/b811821ccecdf9e4029793224dcf765fd0c58fc9))

---

## [3.2.0](https://github.com/linc/nitro-porter/compare/v3.1.2...v3.2.0) (2022-12-10)

* Improve CLI output with progress logs and valid parameter values. ([42e942](https://github.com/linc/nitro-porter/commit/42e9426ca8fc9bafb6c598fed2ca881aa603b178))
* Fix CLI request handling. ([0193ea](https://github.com/linc/nitro-porter/commit/0193ea33f57c81078a888c479c956b43587a13d6))
* Fix case-sensitive detection of PDO class.

---

## [3.1.2](https://github.com/linc/nitro-porter/compare/v3.1.1...v3.1.2) (2022-10-23)

* Fix 'Wysiwyg' and 'Html' formatting from Vanilla to Flarum.

---

## [3.1.1](https://github.com/linc/nitro-porter/compare/v3.1...v3.1.1) (2022-10-22)

* Fix routing error preventing main process from running.

---

## [3.1](https://github.com/linc/nitro-porter/compare/v3.1...v3.1) (2022-10-22)

* Add this changelog.
* Fix avatar locations in Flarum target support.
* Add Spotify embed support for Flarum.
* Add discussion view count support for Flarum.
* Fix performance and other issues importing to blank database for Flarum.
* Fix support for "My media" page in Flarum.
* Fix Kunena source support (thanks to specialworld83)
* Improve feature table to not show 'x' when the feature isn't available.
* Add notes on Flarum and Vanilla feature compatibility.
* Add built-in support pages for Target platform features.

---

## [3.0](https://github.com/linc/nitro-porter/compare/v2.5...v3.0) (2022-09-27)

* Rebuilds entire framework and adds Illuminate database driver.
* Adds target support for Flarum.
* Adds source support for Flarum.
* Adds source support for Vanilla's Rich Text Editor.
* Adds source support for MVC.
* Adds source support for Q2A.
* Fixes multiple minor bugs in source packages.

---

## [2.5](https://github.com/linc/nitro-porter/compare/v2.4...v2.5) (2019-10-25)


---

## [2.4](https://github.com/linc/nitro-porter/compare/v2.3...v2.4) (2018-09-06)


---

## [2.3](https://github.com/linc/nitro-porter/compare/v2.2...v2.3) (2016-06-17)


---

## [2.2](https://github.com/linc/nitro-porter/compare/v2.1.3...v2.2) (2015-06-09)


---

## [2.1.3](https://github.com/linc/nitro-porter/compare/v2.1.2...v2.1.3) (2014-12-06)


---

## [2.1.2](https://github.com/linc/nitro-porter/compare/v2.1.1...v2.1.2) (2014-12-06)


---

## [2.1.1](https://github.com/linc/nitro-porter/compare/v2.1...v2.1.1) (2014-12-05)


---

## [2.1](https://github.com/linc/nitro-porter/compare/v1.3.1...v2.1) (2014-11-22)


---

## [1.3.1](https://github.com/linc/nitro-porter/compare/v1.3.0...v1.3.1) (2010-11-14)


---

## [1.3.0](https://github.com/linc/nitro-porter/compare/v1.2.0...v1.3.0) (2010-11-09)


---

## [1.2.0](https://github.com/linc/nitro-porter/compare/v1.1.2...v1.2.0) (2010-09-08)


---

## [1.1.2](https://github.com/linc/nitro-porter/compare/v1.1...v1.1.2) (2010-09-07)


---

## [1.1](https://github.com/linc/nitro-porter/compare/v1.0.1...v1.1) (2010-07-31)


---

## [1.0.1](https://github.com/linc/nitro-porter/compare/v1.0...v1.0.1) (2010-07-22)


---

## [1.0](https://github.com/linc/nitro-porter/compare/40b55d3a8d29db78aa798d0405270266aacc7972...v1.0) (2010-07-21)


---

