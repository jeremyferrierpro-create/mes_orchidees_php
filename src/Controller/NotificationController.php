<?php
/**
 * Contrôleur des notifications — boîte de réception de l'utilisateur (Phase 03, incrément 4).
 *
 * Gère la consultation, le marquage « lu » et la suppression des notifications du
 * SEUL utilisateur connecté. L'isolation est stricte (même discipline que RG1) :
 * toutes les requêtes du repository filtrent par `user_id`, et l'identifiant du
 * destinataire est lu UNIQUEMENT en session — jamais depuis la requête.
 *
 * Constructeur SANS ARGUMENT (DefaultControllerResolver : `new ClassName()`),
 * dépendance composée ici.
 *
 * @package App\Controller
 * @author  Jérémy Ferrier
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Controller;

use App\Repository\NotificationRepository;

class NotificationController extends AbstractController
{
    private NotificationRepository $notifications;

    public function __construct()
    {
        parent::__construct();
        $this->notifications = new NotificationRepository();
    }

    /**
     * Identifiant de l'utilisateur connecté (isolation).
     *
     * @return string
     */
    private function currentUserId(): string
    {
        return (string) (current_user()['id'] ?? '');
    }

    /**
     * GET /notifications — Liste HTML des notifications de l'utilisateur.
     *
     * @return string HTML rendu.
     */
    public function index(): string
    {
        $uid   = $this->currentUserId();
        $liste = $this->notifications->findByUser($uid);

        return $this->render('pages/notifications/index', [
            'notifications' => $liste,
            'nbNonLues'     => $this->notifications->unreadCount($uid),
            'total'         => count($liste),
            'csrf'          => $this->getCsrfToken(),
        ], 'Mes notifications');
    }

    /**
     * GET /notification/{id} — Détail d'une notification (isolation : WHERE user_id).
     *
     * @param string $id UUID de la notification.
     * @return string HTML rendu.
     */
    public function show(string $id): string
    {
        $uid  = $this->currentUserId();
        $notif = $this->notifications->findByIdForUser($id, $uid);
        if ($notif === null) {
            http_response_code(404);
            return $this->render('pages/error', [
                'status'  => 404,
                'message' => 'Cette notification est introuvable.',
            ], 'Notification introuvable');
        }

        // Affuser la notification fait aussi passer son état à « lue ».
        $this->notifications->markReadForUser($notif->id, $uid);

        return $this->render('pages/notifications/show', [
            'notification' => $notif,
            'csrf'         => $this->getCsrfToken(),
        ], 'Notification');
    }

    /**
     * GET /api/notifications/propositions — Flux JSON des nouvelles propositions
     * non lues destinées au MODÉRATEUR/Admin connecté (toast d'alerte temps réel).
     *
     * Le route est GRANT_MODO : la 403 du front-controller filtre déjà un utilisateur
     * ordinaire. Le destinataire est lu UNIQUEMENT en session (RG1) — jamais depuis la
     * requête —, et la requête serveur filtre `type = 'nouvelle_proposition'` + `is_read
     * = 0`. On ne lit ni ne modifie AUCUNE notification d'un autre compte.
     *
     * Réponse stable (front `features/moderation-toast.js`) :
     *   { status:'ok', count:int, moderationUrl:string, readUrlBase:string, csrf:string,
     *     propositions:[ Notification::toArray() ] }
     * Le jeton CSRF ne voyage que dans cette réponse authentifiée (marquage « lu »).
     *
     * @return string Corps JSON (200).
     */
    public function propositions(): string
    {
        $uid = $this->currentUserId();
        if ($uid === '') {
            return $this->json(['status' => 'ko', 'count' => 0, 'propositions' => []], 403);
        }

        // On plafonne côté serveur : au-delà, l'empilement de toasts monopoliserait l'écran
        // (le lien « Modérer » mène à /moderation où toute la file est visible).
        $liste = $this->notifications->findUnreadByTypeForUser($uid, 'nouvelle_proposition', 5);

        return $this->json([
            'status'        => 'ok',
            'count'         => count($liste),
            'moderationUrl' => url('/moderation'),
            'readUrlBase'   => url('/notification'),
            'csrf'          => $this->getCsrfToken(),
            'propositions'  => array_map(fn(\App\Entity\Notification $n) => $n->toArray(), $liste),
        ]);
    }

    /**
     * POST /notification/{id}/read — Marque la notification comme lue (isolation).
     *
     * Authentifié (grant), CSRF vérifié (`assertCsrf` → 400 sinon), RG1 :
     * `markReadForUser` filtre par `user_id` — la notification d'un tiers est
     * injoignable même en devinant l'UUID. En JSON (toast C1) : 200 {status} ou
     * 404 {status} ; le jeton CSRF invalide reste renvoyé en 400 par le
     * front-controller (le client ne doit alors PAS considérer le rappel comme lu).
     *
     * @param string $id UUID de la notification.
     * @return string|null Corps JSON si le requérant le préfère, sinon rien (redirection).
     */
    public function markRead(string $id): ?string
    {
        $this->assertCsrf($_POST);
        $uid = $this->currentUserId();

        $ok = $this->notifications->markReadForUser($id, $uid);

        if ($this->prefersJson()) {
            return $this->json(
                ['status' => $ok ? 'ok' : 'not_found', 'id' => $id],
                $ok ? 200 : 404
            );
        }

        if ($ok) {
            $this->addFlash('Notification marquée comme lue.', 'success');
        } else {
            $this->addFlash('Notification introuvable.', 'error');
        }
        $this->redirectTo('/notifications');
        return null; // inatteignable (redirectTo termine la requête)
    }

    /**
     * POST /notification/delete/{id} — Supprime la notification (isolation).
     *
     * @param string $id UUID de la notification.
     * @return void (redirection).
     */
    public function delete(string $id): void
    {
        $this->assertCsrf($_POST);
        $uid = $this->currentUserId();

        if ($this->notifications->deleteForUser($id, $uid)) {
            $this->addFlash('Notification supprimée.', 'success');
        } else {
            $this->addFlash('Suppression impossible : notification introuvable.', 'error');
        }
        $this->redirectTo('/notifications');
    }
}
