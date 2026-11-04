# Lunar Product Packs

Products bought together, as an entity of their own: each product with its
base quantity, and each of its options fixed, linked to options of the other
products (by a table of pairs made by hand) or left free.

The plugin holds the data, the admin and the logic, and dispatches
`PackChanged` with the affected products. The storefront renders the packs
(`ProductPacks::forProduct`, `PackDefinition::make`) and handles its own cache.
