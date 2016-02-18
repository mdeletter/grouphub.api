<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class UserNotificationController
 */
class UserNotificationController extends FOSRestController
{
    /**
     * @param int $id
     *
     * @return View
     */
    public function getUserNotificationsAction($id)
    {
        /** @var User $user */
        $user = $this->getDoctrine()->getRepository('AppBundle:User')->find($id);

        if ($user === null) {
            throw new NotFoundHttpException('User with id: ' . $id . ' not found');
        }

        $notifications = $this->getDoctrine()->getRepository('AppBundle:Notification')->findBy(['to' => $user]);

        return $this->view($notifications);
    }

    /**
     * @param int $userId
     * @param int $id
     *
     * @return View
     */
    public function deleteUserNotificationsAction($userId, $id)
    {
        $notification = $this->getDoctrine()->getRepository('AppBundle:Notification')->findOneBy(
            ['to' => $userId, 'id' => $id]
        );

        if ($notification === null) {
            throw new NotFoundHttpException('Notification ' . $id . ' not found for user ' . $userId);
        }

        $em = $this->getDoctrine()->getManager();
        $em->remove($notification);
        $em->flush();

        return $this->routeRedirectView('get_user_notifications', ['id' => $userId]);
    }
}
