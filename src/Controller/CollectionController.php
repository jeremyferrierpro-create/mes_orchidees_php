<?php
/**
 * Contrôleur « Ma Collection » — espace privé de l'utilisateur connecté (Phase 03, incrément 3).
 *
 * Gère le tableau de bord (/macollection), la liste JSON (/collections), la fiche
 * d'une plante (/collection/{id}) et l'ajout / modification / suppression d'une entrée.
 *
 * ISOLATION RG1 : le propriétaire est TOUJOURS dérivé de la session
 * (current_user()['id']) et JAMAIS d'une donnée soumise par le client. Toutes les
 * requêtes passent par des méthodes *ForUser du repository qui filtrent sur user_id.
 *
 * Constructeur SANS ARGUMENT (DefaultControllerResolver), dépendances composées ici.
 *
 * @package App\Controller
 * @author  Jérémy Ferrier
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Controller;

use App\Entity\Collection;
use App\Entity\Conseil;
use App\Entity\Emplacement;
use App\Entity\Notification;
use App\Entity\Orchid;
use App\Repository\CollectionRepository;
use App\Repository\ConseilRepository;
use App\Repository\EmplacementRepository;
use App\Repository\NotificationRepository;
use App\Repository\OrchidRepository;
use App\Repository\SoinRepository;
use App\Repository\TypeSoinRepository;
use App\Repository\UserRepository;
use App\Service\Upload;

class CollectionController extends AbstractController
{
    private CollectionRepository $collections;
    private OrchidRepository $orchids;
    private SoinRepository $soins;
    private NotificationRepository $notifications;
    private UserRepository $users;
    private TypeSoinRepository $typesSoin;
    private ConseilRepository $conseils;
    private EmplacementRepository $emplacements;

    public function __construct()
    {
        parent::__construct();
        $this->collections   = new CollectionRepository();
        $this->orchids       = new OrchidRepository();
        $this->soins         = new SoinRepository();
        $this->notifications = new NotificationRepository();
        $this->users         = new UserRepository();
        $this->typesSoin     = new TypeSoinRepository();
        $this->conseils      = new ConseilRepository();
        $this->emplacements  = new EmplacementRepository();
    }

    /**
     * Identifiant de l'utilisateur connecté (rg1). Le grant 'authenticated' de la
     * route garantit la présence de la session ; on retourne '' si absent (défense).
     *
     * @return string
     */
    private function currentUserId(): string
    {
        return (string) (current_user()['id'] ?? '');
    }

    // ---------------------------------------------------------------------
    // TABLEAU DE BORD (HTML)
    // ---------------------------------------------------------------------

    /**
     * GET /macollection — Tableau de bord de la collection (grant : authentifié).
     *
     * @return string HTML rendu.
     */
    public function dashboard(): string
    {
        $uid = $this->currentUserId();
        // Activation différée : les rappels `planifie` devenus dus passent `actif`
        // à l'affichage du tableau de bord (pas de cron côté mutualisé 0 €/mois).
        $this->notifications->activateDueReminders($uid);

        $collection = $this->collections->findByUser($uid);
        $user       = current_user();

        // Rappels à venir (planifiés + actifs) : indexés par plante (premier par plante)
        // pour la colonne « Rappel / Date rappel » du tableau SOINS ; comptés pour le
        // bandeau NOTIFICATIONS (soins à prévoir = rappels actifs, alertes = rappels en retard).
        $rappelsParPlante = [];
        $soinsAPrevoir = 0;
        $alertes = 0;
        foreach ($this->notifications->findRappelsForUser($uid, ['planifie', 'actif']) as $r) {
            if ($r->collectionId !== null && !isset($rappelsParPlante[$r->collectionId])) {
                $rappelsParPlante[$r->collectionId] = $r;
            }
            if ($r->statut === 'actif') {
                $soinsAPrevoir++;
                if ($r->isLate()) {
                    $alertes++;
                }
            }
        }

        // Historique des soins (toutes plantes confondues), tronqué pour tenir dans la carte.
        $soins = array_slice($this->soins->findByUser($uid), 0, 8);

        // Endroits de culture paramétrés par l'utilisateur : source de la carte CLIMAT.
        $emplacements = $this->emplacements->findByUser($uid);

        // Flux réel des notifications (bandeau NOTIFICATIONS) : on affiche les plus
        // récentes, chacune cliquable vers sa fiche — plus de lignes codées en dur.
        $notificationsFeed = array_slice($this->notifications->findByUser($uid), 0, 4);

        // Nom d'affichage par plante, pour relier chaque ligne du tableau SOINS à sa fiche.
        $nomsPlantes = [];
        foreach ($collection as $item) {
            $nomsPlantes[$item->id] = $item->displayName();
        }

        return $this->render('pages/collection/dashboard', [
            'collection'       => $collection,
            'rappelsParPlante' => $rappelsParPlante,
            'soins'            => $soins,
            // Bandeau supérieur (conforme à la maquette).
            'statsPlantes'     => $this->statsPlantes($collection),
            'climat'           => $this->climatEmplacements($emplacements),
            'emplacements'     => $emplacements,
            'notifications'    => [
                'soins'   => $soinsAPrevoir,
                'alertes' => $alertes,
                'sante'   => $this->notifications->unreadCount($uid),
            ],
            // Carte NOTIFICATIONS : flux réel + carte NOMS des plantes pour le tableau SOINS.
            'notificationsFeed' => $notificationsFeed,
            'nomsPlantes'       => $nomsPlantes,
            // Panneau CONSEILS : fiche « espèce » reliée à une plante possédée.
            'conseil'          => $this->conseilPourCollection($collection),
            // Valeurs botaniques distinctes de l'encyclopédie : alimentent les menus
            // déroulants du formulaire d'ajout libre (genre / famille / comportement).
            'genres'           => $this->orchids->distinctValues('genre'),
            'families'         => $this->orchids->distinctValues('family'),
            'behaviors'        => $this->orchids->distinctValues('behavior'),
            // Espèces publiées : alimentent l'autocomplétion du champ « nom » pour
            // permettre de sélectionner directement une orchidée déjà dans l'encyclopédie.
            'published'        => $this->orchids->findAllPublished(),
            'nbPlantes'        => count($collection),
            'user'             => $user,
            'csrf'             => $this->getCsrfToken(),
            'meta_description' => 'Votre collection personnelle d\'orchidées : plantes, conditions de culture et historique de soins.',
        ], 'Ma Collection');
    }

    /**
     * Répartition des plantes possédées par comportement (bandeau « PLANTES »).
     * Le comportement provient de l'espèce jointe (Collection::$orchidBehavior).
     *
     * @param Collection[] $collection
     * @return array{total:int,epiphytes:int,terrestres:int,hemi:int}
     */
    private function statsPlantes(array $collection): array
    {
        $stats = ['total' => count($collection), 'epiphytes' => 0, 'terrestres' => 0, 'hemi' => 0];
        foreach ($collection as $item) {
            $b = $this->normaliseTexte((string) ($item->orchidBehavior ?? ''));
            if ($b === '') {
                continue;
            }
            // Ordre important : « hemiepiphyte » contient « epiphyte » → testé d'abord.
            if (str_contains($b, 'hemi')) {
                $stats['hemi']++;
            } elseif (str_contains($b, 'epiphyte')) {
                $stats['epiphytes']++;
            } elseif (str_contains($b, 'terrestre') || str_contains($b, 'lithophyte')) {
                $stats['terrestres']++;
            }
        }
        return $stats;
    }

    /**
     * Climat de la collection issu des ENDROITS de culture paramétrés par
     * l'utilisateur. La carte CLIMAT reflète l'ENVIRONNEMENT configuré, JAMAIS une
     * plante : chaque endroit compte UNE fois (moyenne de ses propres constantes
     * structurées), indépendamment du nombre de plantes qui y sont rattachées.
     * Sans endroit paramétré, toutes les valeurs sont null (la vue affiche « — »
     * et propose de configurer un endroit).
     *
     * @param Emplacement[] $emplacements
     * @return array{temp:?string,hygro:?string,light:?string,label:?string,nbEmplacements:int}
     */
    private function climatEmplacements(array $emplacements): array
    {
        $niveauxLumiere = ['faible' => 1, 'moyenne' => 2, 'forte' => 3];
        $temps = [];
        $hygros = [];
        $lumieres = [];
        $noms = [];

        foreach ($emplacements as $e) {
            $t = $e->tempMoyenne();
            if ($t !== null) {
                $temps[] = $t;
            }
            $h = $e->hygroMoyenne();
            if ($h !== null) {
                $hygros[] = $h;
            }
            if ($e->lumiere !== null && isset($niveauxLumiere[$e->lumiere])) {
                $lumieres[] = $niveauxLumiere[$e->lumiere];
            }
            if ($e->nom !== '') {
                $noms[] = $e->nom;
            }
        }

        $nb = count($emplacements);
        return [
            'temp'  => $temps  === [] ? null : (string) round(array_sum($temps)  / count($temps))  . ' °C',
            'hygro' => $hygros === [] ? null : (string) round(array_sum($hygros) / count($hygros)) . ' %',
            'light' => $this->libelleLumiereMoyenne($lumieres),
            'label' => $nb === 0 ? null : ($nb === 1 ? (string) ($noms[0] ?? 'Mon endroit') : $nb . ' endroits'),
            'nbEmplacements' => $nb,
        ];
    }


    /**
     * Libellé du niveau de lumière moyen (FAIBLE / MOYENNE / FORTE), null si aucune donnée.
     *
     * @param int[] $niveaux
     * @return string|null
     */
    private function libelleLumiereMoyenne(array $niveaux): ?string
    {
        if ($niveaux === []) {
            return null;
        }
        $moy = array_sum($niveaux) / count($niveaux);
        if ($moy <= 1.5) {
            return 'FAIBLE';
        }
        if ($moy <= 2.4) {
            return 'MOYENNE';
        }
        return 'FORTE';
    }

    /**
     * Minuscules + accents retirés, pour comparer des libellés librement saisis.
     *
     * @param string $text
     * @return string
     */
    private function normaliseTexte(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        return $ascii !== false ? $ascii : $text;
    }

    /**
     * Première fiche conseil « espèce » reliée à une plante possédée (panneau CONSEILS).
     *
     * @param Collection[] $collection
     * @return Conseil|null
     */
    private function conseilPourCollection(array $collection): ?Conseil
    {
        if ($collection === []) {
            return null;
        }
        $parOrchid = [];
        foreach ($this->conseils->findByType('species') as $c) {
            if ($c->orchidId !== null && !isset($parOrchid[$c->orchidId])) {
                $parOrchid[$c->orchidId] = $c;
            }
        }
        foreach ($collection as $item) {
            if (isset($parOrchid[$item->orchidId])) {
                return $parOrchid[$item->orchidId];
            }
        }
        return null;
    }

    // ---------------------------------------------------------------------
    // PARAMÉTRAGE DES ENDROITS DE CULTURE (page dédiée /macollection/parametres)
    // ---------------------------------------------------------------------

    /**
     * GET /macollection/parametres — Liste des endroits + formulaire (grant : authentifié).
     *
     * @return string HTML rendu.
     */
    public function parametres(): string
    {
        $uid          = $this->currentUserId();
        $emplacements = $this->emplacements->findByUser($uid);

        // Nombre de plantes rattachées à chaque endroit (affichage + garde de suppression).
        $parEndroit = [];
        foreach ($this->collections->findByUser($uid) as $item) {
            if ($item->emplacementId !== null) {
                $parEndroit[$item->emplacementId] = ($parEndroit[$item->emplacementId] ?? 0) + 1;
            }
        }

        return $this->render('pages/collection/parametres', [
            'emplacements'     => $emplacements,
            'parEndroit'       => $parEndroit,
            'lumieres'         => Emplacement::LUMIERES,
            'csrf'             => $this->getCsrfToken(),
            'meta_description' => 'Paramétrez les endroits de culture de votre collection et leurs constantes climatiques.',
        ], 'Paramètres de ma collection');
    }

    /**
     * POST /emplacement/new — Crée un endroit pour l'utilisateur (RG1, CSRF).
     *
     * @return void (redirection).
     */
    public function createEmplacement(): void
    {
        $this->assertCsrf($_POST);
        $uid = $this->currentUserId();

        $e = new Emplacement();
        $e->userId = $uid;
        $this->hydrateEmplacement($e);

        if ($e->nom === '') {
            $this->addFlash('Le nom de l\'emplacement est obligatoire.', 'error');
            $this->redirectTo('/macollection/parametres');
            return;
        }

        $this->emplacements->create($e);
        $this->addFlash('Emplacement ajouté.', 'success');
        $this->redirectTo('/macollection/parametres');
    }

    /**
     * POST /update/emplacement — Met à jour un endroit (RG1, CSRF).
     *
     * @return void (redirection).
     */
    public function updateEmplacement(): void
    {
        $this->assertCsrf($_POST);
        $uid = $this->currentUserId();

        $id = trim((string) ($_POST['id'] ?? ''));
        $e  = $this->emplacements->findByIdForUser($id, $uid);
        if ($e === null) {
            $this->addFlash('Modification impossible : emplacement introuvable.', 'error');
            $this->redirectTo('/macollection/parametres');
            return;
        }

        $this->hydrateEmplacement($e);
        if ($e->nom === '') {
            $this->addFlash('Le nom de l\'emplacement est obligatoire.', 'error');
            $this->redirectTo('/macollection/parametres');
            return;
        }

        $this->emplacements->updateForUser($e, $uid);
        $this->addFlash('Emplacement mis à jour.', 'success');
        $this->redirectTo('/macollection/parametres');
    }

    /**
     * POST /emplacement/delete/{id} — Supprime un endroit (RG1, CSRF).
     * Les plantes rattachées restent en collection, simplement sans endroit (FK SET NULL).
     *
     * @param string $id UUID.
     * @return void (redirection).
     */
    public function deleteEmplacement(string $id): void
    {
        $this->assertCsrf($_POST);
        $uid = $this->currentUserId();

        if ($this->emplacements->deleteForUser($id, $uid)) {
            $this->addFlash('Emplacement supprimé. Les plantes rattachées restent dans votre collection (sans emplacement).', 'success');
        } else {
            $this->addFlash('Suppression impossible : emplacement introuvable.', 'error');
        }
        $this->redirectTo('/macollection/parametres');
    }

    /**
     * Renseigne un endroit depuis le formulaire (saisie numérique structurée, RG1 côté id).
     *
     * @param Emplacement $e
     * @return void
     */
    private function hydrateEmplacement(Emplacement $e): void
    {
        $e->nom         = trim((string) ($_POST['nom'] ?? ''));
        $e->tempMin     = $this->parseNombre($_POST['temp_min'] ?? null);
        $e->tempMax     = $this->parseNombre($_POST['temp_max'] ?? null);
        $e->hygroMin    = $this->parsePourcent($_POST['hygro_min'] ?? null);
        $e->hygroMax    = $this->parsePourcent($_POST['hygro_max'] ?? null);
        $lumiere        = trim((string) ($_POST['lumiere'] ?? ''));
        $e->lumiere     = in_array($lumiere, Emplacement::LUMIERES, true) ? $lumiere : null;
        $e->ventilation = $this->strOrNull($_POST['ventilation'] ?? null);
    }

    /**
     * Convertit une saisie numérique libre (accepte la virgule) en float, null si vide/aberrant.
     *
     * @param mixed $value
     * @return float|null
     */
    private function parseNombre(mixed $value): ?float
    {
        $value = is_scalar($value) ? trim((string) $value) : '';
        if ($value === '') {
            return null;
        }
        $value = str_replace(',', '.', $value);
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Convertit une saisie de pourcentage en entier borné à [0 ; 100], null si vide/aberrant.
     *
     * @param mixed $value
     * @return int|null
     */
    private function parsePourcent(mixed $value): ?int
    {
        $nombre = $this->parseNombre($value);
        if ($nombre === null) {
            return null;
        }
        return (int) max(0, min(100, round($nombre)));
    }

    /**
     * GET /collections — Liste JSON des plantes de l'utilisateur (grant : authentifié).
     *
     * @return string JSON.
     */
    public function index(): string
    {
        $uid  = $this->currentUserId();
        $list = array_map(fn(Collection $c) => $c->toArray(), $this->collections->findByUser($uid));
        return $this->json($list);
    }

    /**
     * GET /collection/{id} — Fiche d'une plante + historique de soins (grant : authentifié).
     *
     * RG1 : findByIdForUser() renvoie null si la plante appartient à un autre utilisateur.
     *
     * @param string $id UUID de l'entrée.
     * @return string HTML rendu.
     */
    public function show(string $id): string
    {
        $uid  = $this->currentUserId();
        $item = $this->collections->findByIdForUser($id, $uid);
        if ($item === null) {
            http_response_code(404);
            return $this->render('pages/error', [
                'status'  => 404,
                'message' => 'Cette plante est introuvable dans votre collection.',
            ], 'Plante introuvable');
        }

        // Activation différée puis rappel à venir pour cette plante.
        $this->notifications->activateDueReminders($uid);
        $prochainRappel = null;
        foreach ($this->notifications->findRappelsForUser($uid, ['planifie', 'actif']) as $r) {
            if ($r->collectionId === $item->id) {
                $prochainRappel = $r;
                break;
            }
        }

        return $this->render('pages/collection/show', [
            'item'           => $item,
            'soins'          => $this->soins->findByCollectionForUser($item->id, $uid),
            'typesSoin'      => $this->typesSoin->findAll(),
            'prochainRappel' => $prochainRappel,
            'emplacements'   => $this->emplacements->findByUser($uid),
            'csrf'           => $this->getCsrfToken(),
        ], $item->displayName());
    }

    // ---------------------------------------------------------------------
    // AJOUT
    // ---------------------------------------------------------------------

    /**
     * POST /collection/new — Ajoute une orchidée à la collection de l'utilisateur.
     *
     * Ajout LIBRE : l'utilisateur saisit un nom (et éventuellement une photo).
     *  - si une espèce PUBLIÉE correspond (nom/slug) → on la relie simplement ;
     *  - sinon → on CRÉE UNE PROPOSITION non publiée (moderation_status = 'pending'),
     *    on l'ajoute QUAND MÊME à la collection de l'utilisateur, et on NOTIFIE les
     *    administrateurs/modérateurs. La proposition rejoindra l'encyclopédie seulement
     *    si la modération l'accepte (cf. ModerationController).
     *
     * La photo est toujours enregistrée dans la collection ; elle sert aussi
     * d'illustration à la fiche proposée (visible en encyclopédie une fois validée).
     *
     * @return void (redirection).
     */
    public function create(): void
    {
        $this->assertCsrf($_POST);
        $uid = $this->currentUserId();

        // Photo personnelle : upload optionnel, validée par le Service Upload.
        try {
            $photo = Upload::image($_FILES['photo'] ?? null);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->addFlash($e->getMessage(), 'error');
            $this->redirectTo('/macollection');
            return;
        }

        // 1) Rétro-compatibilité : espèce déjà choisie via un id publié.
        $orchidId = trim((string) ($_POST['orchid_id'] ?? ''));
        if ($orchidId !== '' && $this->orchids->findById($orchidId, true) !== null) {
            $this->addToCollection($uid, $orchidId, $photo, 'Plante ajoutée à votre collection.');
            return;
        }

        // 2) Ajout libre par nom saisi.
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $this->addFlash('Indiquez le nom de l\'orchidée à ajouter.', 'error');
            $this->redirectTo('/macollection');
            return;
        }
        $slug = $this->slugify($name);

        // 2a) Une espèce publiée correspond → simple liaison (pas de proposition).
        $published = $this->orchids->findById($slug, true);
        if ($published !== null) {
            $this->addToCollection($uid, $published->id, $photo, 'Plante ajoutée à votre collection.');
            return;
        }

        // 2b) Le nom peut aussi correspondre à un slug fourni explicitement (formulaire avancé).
        $postedSlug = $this->slugify(trim((string) ($_POST['id'] ?? '')));
        if ($postedSlug !== '') {
            $bySlug = $this->orchids->findById($postedSlug, true);
            if ($bySlug !== null) {
                $this->addToCollection($uid, $bySlug->id, $photo, 'Plante ajoutée à votre collection.');
                return;
            }
        }

        // Nouvelle espèce : les champs botaniques sont obligatoires (aucun champ vide).
        $genre    = trim((string) ($_POST['genre'] ?? ''));
        $family   = trim((string) ($_POST['family'] ?? ''));
        $behavior = trim((string) ($_POST['behavior'] ?? ''));
        if ($genre === '' || $family === '' || $behavior === '') {
            $this->addFlash('Complétez le genre, la famille et le comportement pour proposer une nouvelle espèce.', 'error');
            $this->redirectTo('/macollection');
            return;
        }

        // Une fiche (pending/rejected) porte déjà ce slug → on la relie, sans doublon.
        if ($this->orchids->exists($slug)) {
            $this->addToCollection($uid, $slug, $photo, 'Plante ajoutée à votre collection.');
            return;
        }

        // 2c) Création d'une PROPOSITION en attente + notification des admins.
        $orchid = new Orchid();
        $orchid->id               = $slug;
        $orchid->name             = mb_strtoupper($name);
        $orchid->genre            = $genre;
        $orchid->family           = $family;
        $orchid->behavior         = $behavior;
        $orchid->vernacular       = $this->strOrNull($_POST['vernacular'] ?? null);
        $orchid->species          = $this->strOrNull($_POST['species'] ?? null);
        $orchid->origin           = $this->strOrNull($_POST['origin'] ?? null);
        $orchid->shortDesc        = $this->strOrNull($_POST['short_desc'] ?? null);
        $orchid->img              = $photo;
        $orchid->isPublished      = false;
        $orchid->moderationStatus = 'pending';
        $orchid->proposedBy       = $uid;
        $this->orchids->create($orchid);

        $this->notifierAdministrateurs($orchid->name);

        $this->addToCollection(
            $uid,
            $slug,
            $photo,
            'Plante ajoutée à votre collection. Votre proposition a été transmise à la modération : '
            . 'la photo est enregistrée dans votre collection et rejoindra l\'encyclopédie si la proposition est validée.'
        );
    }

    /**
     * Ajoute réellement une espèce (publiée ou proposée) à la collection, avec sa photo,
     * puis redirige vers la fiche. Contrôle anti-doublon RG1.
     *
     * @param string      $uid       Propriétaire (session).
     * @param string      $orchidId  Slug de l'espèce.
     * @param string|null $photo     Chemin de la photo uploadee (ou null).
     * @param string      $flash     Message de succès à afficher.
     * @return void
     */
    private function addToCollection(string $uid, string $orchidId, ?string $photo, string $flash): void
    {
        if ($this->collections->existsForUser($uid, $orchidId)) {
            $this->addFlash('Cette espèce est déjà dans votre collection.', 'error');
            $this->redirectTo('/macollection');
            return;
        }

        $c = new Collection();
        $c->userId   = $uid;        // TOUJOURS l'utilisateur de la session — RG1.
        $c->orchidId = $orchidId;
        $c->photo    = $photo;
        $this->fillFields($c);

        $newId = $this->collections->create($c);
        $this->addFlash($flash, 'success');
        $this->redirectTo('/collection/' . rawurlencode($newId));
    }

    /**
     * Notifie chaque admin/modérateur d'une nouvelle proposition à modérer.
     *
     * @param string $name Nom (normalisé) de l'espèce proposée.
     * @return void
     */
    private function notifierAdministrateurs(string $name): void
    {
        $message = sprintf('Nouvelle proposition d\'orchidée à modérer : « %s ».', $name);
        foreach ($this->users->findIdsByRole(['admin', 'moderateur']) as $adminId) {
            $n = new Notification();
            $n->userId       = $adminId;
            $n->collectionId = null;
            $n->type         = 'nouvelle_proposition';
            $n->message      = $message;
            $n->isRead       = false;
            $n->notifDate    = date('Y-m-d H:i:s');
            $this->notifications->create($n);
        }
    }

    /**
     * Fabrique un slug d'identifiant à partir d'un nom (minuscules, tiret bas).
     *
     * Règle héritée du catalogue : « Acacalis Cyanea » → « acacalis_cyanea ».
     *
     * @param string $text
     * @return string
     */
    private function slugify(string $text): string
    {
        $slug = strtolower($text);
        $slug = strtr($slug, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
            'ñ' => 'n', 'ÿ' => 'y',
        ]);
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        return trim((string) $slug, '_');
    }

    // ---------------------------------------------------------------------
    // MODIFICATION
    // ---------------------------------------------------------------------

    /**
     * POST /update/collection — Met à jour une entrée de la collection (RG1).
     *
     * @return void (redirection).
     */
    public function update(): void
    {
        $this->assertCsrf($_POST);
        $uid = $this->currentUserId();

        $id     = trim((string) ($_POST['id'] ?? ''));
        $current = $this->collections->findByIdForUser($id, $uid);
        if ($current === null) {
            $this->addFlash('Modification impossible : plante introuvable dans votre collection.', 'error');
            $this->redirectTo('/macollection');
        }

        $orchidId = trim((string) ($_POST['orchid_id'] ?? ''));
        if ($orchidId !== '' && $this->orchids->findById($orchidId, true) !== null) {
            $current->orchidId = $orchidId;
        }
        $this->fillFields($current);

        $this->collections->updateForUser($current, $uid);
        $this->addFlash('Plante mise à jour.', 'success');
        $this->redirectTo('/collection/' . rawurlencode($current->id));
    }

    // ---------------------------------------------------------------------
    // SUPPRESSION
    // ---------------------------------------------------------------------

    /**
     * POST /collection/delete/{id} — Retire une plante de la collection (RG1).
     *
     * @param string $id UUID.
     * @return void (redirection).
     */
    public function delete(string $id): void
    {
        $this->assertCsrf($_POST);
        $uid = $this->currentUserId();

        if ($this->collections->deleteForUser($id, $uid)) {
            $this->addFlash('Plante retirée de votre collection.', 'success');
        } else {
            $this->addFlash('Suppression impossible : plante introuvable.', 'error');
        }
        $this->redirectTo('/macollection');
    }

    // ---------------------------------------------------------------------
    // AIDE PRIVÉE : hydrate les champs libres depuis $_POST
    // ---------------------------------------------------------------------

    /**
     * Renseigne les champs contextuels d'une entrée depuis le formulaire.
     *
     * @param Collection $c
     * @return void
     */
    private function fillFields(Collection $c): void
    {
        $c->location    = $this->strOrNull($_POST['location'] ?? null);
        $c->notes       = $this->strOrNull($_POST['notes'] ?? null);
        $c->temp        = $this->strOrNull($_POST['temp'] ?? null);
        $c->hygro       = $this->strOrNull($_POST['hygro'] ?? null);
        $c->light       = $this->strOrNull($_POST['light'] ?? null);
        $c->ventilation = $this->strOrNull($_POST['ventilation'] ?? null);

        // Endroit de culture rattaché. On ne retient que la valeur explicite « aucun »
        // (chaîne vide → null) ou un endroit possédé par l'utilisateur (RG1 : la FK
        // refuserait un id tiers ; on le valide ici pour un comportement propre).
        if (array_key_exists('emplacement_id', $_POST)) {
            $eid = trim((string) $_POST['emplacement_id']);
            if ($eid === '') {
                $c->emplacementId = null;
            } elseif ($this->emplacements->findByIdForUser($eid, $this->currentUserId()) !== null) {
                $c->emplacementId = $eid;
            }
        }

        // Fréquence de rappel (RG3 personnalisé) : bornée à [1 ; 365].
        // Absence du champ ou valeur aberrante → valeur actuelle, jamais 0.
        if (isset($_POST['rappel_frequence_jours']) && $_POST['rappel_frequence_jours'] !== '') {
            $v = (int) $_POST['rappel_frequence_jours'];
            if ($v >= 1 && $v <= 365) {
                $c->rappelFrequenceJours = $v;
            }
        }
    }

    /**
     * Retourne la valeur trimée, ou null si chaîne vide.
     *
     * @param mixed $value
     * @return string|null
     */
    private function strOrNull(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';
        return $value === '' ? null : $value;
    }
}
