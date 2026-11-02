# My UNA World — Marketplace Release Contract

## Product identity

- **Product title:** My UNA World
- **Marketplace Name:** `my_una_world`
- **Vendor:** MyUNAWorld
- **Platform:** UNA CMS
- **Price:** Free

The Marketplace **Name** must exactly match the module's internal `install/config.php` `name` value. Do not rename `my_una_world` for branding purposes.

## Downloaded → Info requirement

The native UNA Studio product-information experience under:

**Studio → Apps Market → Downloaded → My UNA World → Info**

is a mandatory release requirement.

Before release, confirm that:

1. The module card shows the correct icon, title, vendor and version.
2. Clicking the module opens the native UNA product-information popup.
3. The popup does not display “Cannot load product info. Try again later.”
4. Product title, vendor, version, category, description and screenshots load correctly.
5. Marketplace media contains no broken images.
6. Marketplace package version matches `install/config.php` exactly.

## Required Marketplace assets

- Studio SVG icon.
- Marketplace header/cover artwork.
- Product screenshots suitable for the native Info popup.
- Short description.
- Full Marketplace description.
- Full installer ZIP for the current version.
- Native updater ZIPs for each supported prior version.
- Release checksum.

## Vendor standard

All displayed vendor/author branding must use:

**MyUNAWorld**

Legacy technical namespaces, module paths, database prefixes and PHP class prefixes are preserved unless a deliberate compatibility migration is performed.

## Safety notice

Back up your website before attempting repairs, upgrades, restores or recovery operations. A current verified backup protects the site if a change does not complete as expected.
