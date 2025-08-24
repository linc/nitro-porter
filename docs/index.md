# Nitro Porter — free your community!
Nitro Porter is the **only** multi-platform community migration tool.

## Target software supported
Nitro Porter can currently migrate your community to these platforms:

![Flarum](assets/logos/flarum-300x100.png)
![Vanilla](assets/logos/vanilla-300x100.png)
![Waterhole](assets/logos/waterhole-300x100.png)

## Source software supported
Nitro Porter can migrate your community away from these platforms:

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

## What data gets migrated?

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
