# Textes de la fiche WordPress.org (français)

Traduction de `weadu-shopping-feed/readme.txt`, pour référence.

## Nom de l'extension

WeAdU Shopping Feed Check

## Description courte

Vérifie les champs produits WooCommerce utilisés par un flux Google Shopping et liste, produit par produit, ce qu'il faut corriger. Fonctionne dans l'administration, n'envoie rien.

## Description

WeAdU Shopping Feed Check lit vos produits WooCommerce publiés et liste, pour chacun, les problèmes que Google Shopping signalerait probablement.

Erreurs : identifiant, titre, description, lien, image, disponibilité ou prix vides ; titre de plus de 150 caractères ; description de plus de 5 000 caractères ; lien ou image qui ne commence pas par http:// ou https:// ; prix nul ou négatif ; GTIN dont la longueur ou la clé de contrôle est fausse ; identifiant en double.

Avertissements : titre de moins de 15 caractères, tout en capitales ou avec une formule promotionnelle comme « livraison gratuite » ou « soldes » ; prix sans code devise ; marque vide ; GTIN et MPN vides ; état vide ou inconnu.

L'extension propose aussi un titre qui commence par la marque quand celle-ci n'apparaît pas dans le titre, peut ajouter vos paramètres UTM aux liens vérifiés et exporte le résultat en CSV. L'interface est en français et en anglais.

L'extension n'appelle ni Google, ni Merchant Center, ni aucun autre service, n'envoie aucune donnée et ne modifie pas vos produits. Un produit sans constat n'est pas validé pour autant par Google.
