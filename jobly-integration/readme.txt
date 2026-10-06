=== Jobly.si — prijavni obrazec ===
Contributors: joblyhub
Tags: jobs, careers, job board, application form, zaposlitev
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Prijavni obrazec in karierna stran podjetja z Jobly.si na vaši WordPress strani.

== Description ==

Kratka koda `[jobly]` prikaže odprta mesta vašega podjetja z Jobly.si in obrazec za prijavo. Kandidat se prijavi brez računa; prijava pride v vaš Jobly postopek kot vsaka druga.

Obrazec, privolitve in zaščito pred neželeno pošto upravlja Jobly. Vaša WordPress stran podatkov kandidatov ne obdeluje in ne shranjuje.

Primeri:

* `[jobly]` — vsa odprta mesta podjetja iz nastavitev
* `[jobly company="drugo-podjetje"]` — karierna stran drugega podjetja
* `[jobly job="slug-oglasa"]` — obrazec za en oglas
* `[jobly job="slug-oglasa" show="full"]` — z vsebino oglasa
* `[jobly filter="title,meta,benefits"]` — samo izbrani deli (logo, title, meta, salary, description, responsibilities, requirements, benefits)
* `[jobly accent="#2563eb" height="900"]`

== External services ==

Vtičnik naloži vsebino s storitve Jobly.si (https://jobly.si) v okvirju (iframe) na straneh, kjer je kratka koda. Brskalnik obiskovalca pri tem pošlje zahtevo na jobly.si. Pogoji: https://jobly.si/terms · Zasebnost: https://jobly.si/privacy

== Installation ==

1. Naloži mapo `jobly-integration` v `/wp-content/plugins/` in vtičnik vklopi.
2. Nastavitve → Jobly.si: vpiši slug podjetja (zadnji del naslova jobly.si/companies/…).
3. Na stran dodaj kratko kodo `[jobly]`.

== Changelog ==

= 0.1.0 =
* Kratka koda `[jobly]` in stran z nastavitvami.
