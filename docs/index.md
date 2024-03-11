# Nitro Porter

**Nitro Porter is a multi-platform community migration tool.**

Nitro Porter supports over 30 **[sources](/sources)** (the software you're leaving) and 3 **[targets](/targets)** (the software you're moving to).

All data sources & targets support users, user roles, discussions, posts, and basic taxonomy ("categories" or "subforums" or "tags" depending on the platform).
Beyond that, each supports different types of data depending on feature availability, extension choice, and maturity of the source/target package.
These include things like badges, reactions, bookmarks, and polls.

Both the source and target must support a data type for it to transfer.

Nitro Porter never transfers permissions. It's not safe to do so automatically due to variations in how platforms implement them.

## Project goals & motivation

Community software has high lock-in due to the difficulty of preserving history through a data migration.

Community history is vitally important, and being able to change software is important for the health of the community software ecosystem.

Nitro Porter exists because your community deserves both the best tools available and the continuity of its unique history.
It uses the GNU Public License 2.0 to ensure it remains freely available to anyone who needs it.

This tool is designed for ease of extensibility to allow anyone with basic programming skills to add a source or target.
Any generally available forum software (commercial or free) may be added as a source or target.
It does not include bespoke or custom forum software, but is designed to allow individuals to create such support easily for their private use.

## History

Nitro Porter was created in 2010 at Vanilla Forums, Inc. as "Vanilla Porter". 
The first several versions only exported to the Vanilla flat file format, which then Vanilla core would import and do final data manipulations.
Lincoln built the first source package for vBulletin, and several other engineers (both internal to Vanilla and OSS contributors) built additional packages.

Originally designed as a single-file "lifeboat" for rescuing forum data on older webservers, it morphed into an efficient CLI-based export tool.
Over a decade, export ("source") support was added for many forum packages, eventually exceeding thirty platforms.

By late 2019, Vanilla had ceased creating packaged open source releases and Vanilla Porter was receiving only minimal updates.

In September 2021, Lincoln forked the GPL2 project as Nitro Porter and rebuilt it into a general-purpose migration pipeline.
It continues to use Vanilla's database schema as an intermediary format to allow backwards compatibility with the source packages already created.
