<?php
declare(strict_types=1);

// Jeux de données partagés pour le seed : prénoms mixtes, noms, villes françaises, bios.
// Utilisé par db/seed.php (génération complète) et db/humanize_seed.php (rafraîchissement en place).

$firstNamesMale = [
    'Lucas', 'Hugo', 'Léo', 'Gabriel', 'Louis', 'Raphaël', 'Théo', 'Jules', 'Arthur', 'Tom',
    'Nathan', 'Adam', 'Antoine', 'Maxime', 'Clément', 'Paul', 'Baptiste', 'Julien', 'Mathis', 'Quentin',
    'Romain', 'Nicolas', 'Victor', 'Charles', 'Alexandre', 'Thomas', 'Noé', 'Sacha', 'Léon', 'Émile',
    'Marius', 'Simon', 'Tristan', 'Yanis', 'Thibault', 'Dylan', 'Rémi', 'Mattéo', 'Gabin', 'Aaron',
];

$firstNamesFemale = [
    'Emma', 'Jade', 'Louise', 'Alice', 'Chloé', 'Lina', 'Léa', 'Manon', 'Jeanne', 'Camille',
    'Zoé', 'Anna', 'Rose', 'Inès', 'Charlotte', 'Sarah', 'Ninon', 'Éva', 'Maé', 'Clémence',
    'Juliette', 'Margaux', 'Anaïs', 'Sofia', 'Lucie', 'Clara', 'Colette', 'Élodie', 'Amélie', 'Laura',
    'Céline', 'Nina', 'Océane', 'Mathilde', 'Apolline', 'Ambre', 'Lila', 'Élise', 'Capucine', 'Héloïse',
];

$firstNamesNeutral = ['Alix', 'Charlie', 'Lou', 'Robin', 'Noa', 'Soizic', 'Éden', 'Dominique'];

$lastNames = [
    'Martin', 'Bernard', 'Dubois', 'Thomas', 'Robert', 'Richard', 'Petit', 'Durand', 'Leroy', 'Moreau',
    'Simon', 'Laurent', 'Lefebvre', 'Michel', 'Garcia', 'David', 'Bertrand', 'Roux', 'Vincent', 'Fournier',
    'Morel', 'Girard', 'André', 'Mercier', 'Dupont', 'Lambert', 'Bonnet', 'Fontaine', 'Rousseau', 'Blanchard',
    'Guérin', 'Muller', 'Henry', 'Roussel', 'Perrin', 'Morin', 'Mathieu', 'Garnier', 'Chevalier', 'Faure',
];

$cities = [
    'Paris' => [48.8566, 2.3522],
    'Lyon' => [45.7640, 4.8357],
    'Marseille' => [43.2965, 5.3698],
    'Bordeaux' => [44.8378, -0.5792],
    'Lille' => [50.6292, 3.0573],
    'Nantes' => [47.2184, -1.5536],
    'Toulouse' => [43.6047, 1.4442],
    'Nice' => [43.7102, 7.2620],
    'Rennes' => [48.1173, -1.6778],
    'Strasbourg' => [48.5734, 7.7521],
    'Montpellier' => [43.6108, 3.8767],
    'Grenoble' => [45.1885, 5.7245],
    'Dijon' => [47.3220, 5.0415],
    'Tours' => [47.3941, 0.6848],
    'Reims' => [49.2583, 4.0317],
    'Clermont-Ferrand' => [45.7772, 3.0870],
    'Angers' => [47.4784, -0.5632],
    'Le Mans' => [48.0061, 0.1996],
];

$bioTemplates = [
    "Fan de {tag}, et très bon public pour faire la conversation.",
    "Si tu cherches un binôme de {tag}, tu frappes à la bonne porte.",
    "Le {tag} occupe mes week-ends, le reste se découvre en discutant.",
    "{tag} depuis toujours. Avis aux amateurs !",
    "Grande fan de {tag}. Le combo gagnant ?",
    "Je viens d'arriver à {city} pour le {tag}, je reste pour la compagnie.",
    "{tag}, cuisine et longues promenades : la liste est ouverte.",
    "On dit que parler de {tag} est ma spécialité. À vérifier.",
    "Fan de {tag} et de bien manger. Le reste, à inventer ensemble.",
    "Attention : dès qu'on parle de {tag}, je ne m'arrête plus.",
    "Bienvenue dans mon monde fait de {tag} et de farniente.",
    "Le dimanche {tag} + brunch, c'est sacré.",
];

$tags = ['vegan', 'geek', 'travel', 'sports', 'music', 'movies', 'gaming', 'art', 'coding', 'fitness', 'foodie', 'books'];
$orientations = ['heterosexual', 'homosexual', 'bisexual'];

/**
 * Renvoie une identité réaliste cohérente : prénom selon le genre, nom,
 * ville avec coordonnées proches du centre, birthdate et bio française.
 *
 * @return array{first:string,last:string,city:string,lat:float,lng:float,birthdate:string,bio:string}
 */
function generateIdentity(?string $gender): array
{
    global $fixtureDataForConsumers;
    $d = $fixtureDataForConsumers;
    $male = $fixtureDataForConsumers['male'];
    $female = $fixtureDataForConsumers['female'];
    $neutral = $fixtureDataForConsumers['neutral'];

    if ($gender === 'male') {
        $first = $male[array_rand($male)];
    } elseif ($gender === 'female') {
        $first = $female[array_rand($female)];
    } else {
        // 50 % prénom explicitement neutre, 50 % prénom classique quel que soit le genre
        $first = mt_rand(0, 1)
            ? $neutral[array_rand($neutral)]
            : (mt_rand(0, 1) ? $male[array_rand($male)] : $female[array_rand($female)]);
    }

    $last = $d['lastNames'][array_rand($d['lastNames'])];
    $cityNames = array_keys($d['cities']);
    $city = $cityNames[array_rand($cityNames)];
    [$centerLat, $centerLng] = $d['cities'][$city];
    $lat = $centerLat + (mt_rand(-1500, 1500) / 10000);
    $lng = $centerLng + (mt_rand(-1500, 1500) / 10000);

    $birthYear = mt_rand(1980, 2005);
    $birthdate = sprintf('%d-%02d-%02d', $birthYear, mt_rand(1, 12), mt_rand(1, 28));

    $tag = $d['tags'][array_rand($d['tags'])];
    $bio = str_replace(
        ['{first}', '{tag}', '{city}'],
        [$first, $tag, $city],
        $d['bioTemplates'][array_rand($d['bioTemplates'])]
    );

    return [
        'first' => $first,
        'last' => $last,
        'city' => $city,
        'lat' => $lat,
        'lng' => $lng,
        'birthdate' => $birthdate,
        'bio' => $bio,
    ];
}

/** Identité en version "slug" pour username/e-mail (sans accents). */
function slugifyName(string $name): string
{
    $map = ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'œ' => 'oe', 'æ' => 'ae', 'ÿ' => 'y'];
    $slug = strtolower(strtr(trim($name), $map));
    $slug = preg_replace('/[^a-z0-9]+/', '', $slug) ?? '';
    return $slug !== '' ? $slug : 'user';
}

/** Données normalisées pour les consommateurs des fixtures. */
$fixtureDataForConsumers = [
    'male' => $firstNamesMale,
    'female' => $firstNamesFemale,
    'neutral' => $firstNamesNeutral,
    'lastNames' => $lastNames,
    'cities' => $cities,
    'bioTemplates' => $bioTemplates,
    'tags' => $tags,
    'orientations' => $orientations,
];
