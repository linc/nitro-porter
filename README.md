Nitro Porter 🚀
==============

The only multi-platform community migration tool. Free your forum!

Nitro Porter is based on PHP 8.2+ and runs via the command line.

## Documentation

### Getting started

* [User Guide](https://nitroporter.org/guide) for requirements & install steps.
* [Migration Guide](https://nitroporter.org/migrations) to plan a community migration.
* [Sources](https://nitroporter.org/sources) & [Targets](https://nitroporter.org/targets) detail what's supported.
* [Start a Discussion](https://github.com/linc/nitro-porter/discussions) to share how it went.

### Getting involved

* [Contribute](docs/contribute.md) data, requests, fixes, or success stories.
* [Changelog](CHANGELOG.md) has latest fixes & updates.
* [Roadmap](https://github.com/users/linc/projects/2) contains informal goals, not ETAs.
* [History](docs/history.md) gives more context to the project.

### Mission statement

Community history is vitally important, and being able to change software is important for the health of the ecosystem.
However, community software often has high lock-in due to the difficulty of a data migration.

Nitro Porter exists because your community deserves both the best tools available and its unique history.
It uses the GNU Public License 2.0 to ensure it remains freely available to anyone who needs it.

This tool is designed for ease of extensibility to allow anyone with basic programming skills to add a source or target.
Any generally available forum software (commercial or free) may be added as a source or target.
It does not include bespoke or custom forum software, but is designed to allow individuals to create such support easily for their private use.

## What's Supported So Far?

### Targets ([3](https://nitroporter.org/targets))

![Flarum](assets/logos/flarum-300x100.png)
![Vanilla](assets/logos/vanilla-300x100.png)
![Waterhole](assets/logos/waterhole-300x100.png)

### Sources ([37](https://nitroporter.org/sources))
* bbPress
* Drupal
* Flarum
* FluxBB
* IPBoard
* MyBB
* NodeBB
* phpBB
* Simple Machines (SMF)
* Vanilla
* vBulletin
* XenForo
* _...[and MORE](https://nitroporter.org/sources)!_

### What gets migrated?

All sources & targets support migrating:
* users & roles
* discussions (or _threads_)
* posts (or _comments_)
* categories (or _subforums_, _channels_, etc.)

Beyond that, each supports **different types of data** depending on feature availability, extension choice, and maturity of the source/target package.
These include things like badges, reactions, bookmarks, and polls.

**_Both the source and target must support a data type for it to transfer!_**

Nitro Porter **never** transfers permissions. It's not safe to do so automatically due to variations in how platforms implement them.
You will **always** need to reassign permissions after a migration.

### Future support

Don't see your software? [Start a discussion](https://github.com/linc/nitro-porter/discussions/new) to request it and keep an eye on our [informal roadmap](https://github.com/users/linc/projects/2).
We're happy to add a new **Source** for any software, provided it is not bespoke.
For a new **Target**, we require support from the vendor if it is not free and open source software.

Currently, all data sources and targets are based on SQL databases (except the Vanilla target's flat file)
and only natively supports MySQL-compatible connections. All other storage formats (like mbox or ASP Playground's MSSQL) 
requires pre-work to convert the data to a MySQL database.

In the future, we plan to natively support:
* PostgreSQL
* MSSQL
* MongoDB
* Web-based APIs

The 3.0 rewrite of Nitro Porter was done with that future in mind.
