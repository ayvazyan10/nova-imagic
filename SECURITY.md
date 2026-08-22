# Security Policy

## Supported versions

| Version | Security fixes |
| --- | --- |
| 2.x | Yes |
| 1.x | No |

Version 1.x reached end of life with the 2.0 release. Upgrade before reporting
an issue that affects only 1.x.

## Reporting a vulnerability

Do not open a public issue for a suspected vulnerability. Email
[ayvazyan403@gmail.com](mailto:ayvazyan403@gmail.com) with:

- the affected Imagic version and environment;
- a concise description of the impact and attack path;
- reproduction steps or a minimal proof of concept;
- whether the issue is already public; and
- a safe way to contact you.

Do not attach credentials, Nova license keys, customer media, or sensitive
production data. You should receive an acknowledgement within seven days. The
maintainer will coordinate validation, a fix, and disclosure timing with you.

## Deployment responsibilities

Imagic limits media-manager records and operations to the authenticated Nova
user. Application owners remain responsible for:

- restricting Nova access with its authorization gate;
- configuring least-privilege filesystem credentials and bucket policies;
- choosing public or private visibility appropriate for the uploaded data;
- keeping sensitive local uploads outside directories exposed by the web
  server, because a visibility flag cannot protect an already public mount;
- keeping Laravel, Nova, Intervention Image, PHP, and image-processing system
  libraries patched;
- setting upload, image dimension, and pixel limits appropriate for available
  memory; and
- treating URLs from public disks as public data.

Do not expose the package's Nova vendor routes outside the application's normal
Nova authentication middleware.
