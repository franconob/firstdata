<?php

namespace FData\SecurityBundle\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\SecurityContextInterface;

class DefaultController extends Controller
{
    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function loginAction(Request $request)
    {
        $session = $request->getSession();

        if ($request->attributes->has(SecurityContextInterface::AUTHENTICATION_ERROR)) {
            $error = $request->attributes->get(
                SecurityContextInterface::AUTHENTICATION_ERROR
            );
        } elseif (null !== $session && $session->has(SecurityContextInterface::AUTHENTICATION_ERROR)) {
            $error = $session->get(SecurityContextInterface::AUTHENTICATION_ERROR);
            $session->remove(SecurityContextInterface::AUTHENTICATION_ERROR);
        } else {
            $error = '';
        }

        return $this->render(
            'FDataSecurityBundle:Default:login.html.twig',
            [
                "error" => $error,
            ]
        );
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function loginExtranetAction(Request $request)
    {
        $session = $request->getSession();

        if ($request->attributes->has(SecurityContextInterface::AUTHENTICATION_ERROR)) {
            $error = $request->attributes->get(
                SecurityContextInterface::AUTHENTICATION_ERROR
            );
        } elseif (null !== $session && $session->has(SecurityContextInterface::AUTHENTICATION_ERROR)) {
            $error = $session->get(SecurityContextInterface::AUTHENTICATION_ERROR);
            $session->remove(SecurityContextInterface::AUTHENTICATION_ERROR);
        } else {
            $error = '';
        }

        return $this->render(
            'FDataSecurityBundle:Default:login_extranet.html.twig',
            [
                "error" => $error,
            ]
        );
    }

    public function changePasswordAction(Request $request)
    {
        $user            = $this->getUser();
        $data            = json_decode($request->getContent(), true);
        $currentPassword = $data['current'];
        $newPassword     = $data['new_password'];
        $userPassword    = $user->getPassword();

        if ($currentPassword === $userPassword) {
            /** @var Connection $con */
            $con  = $this->get('doctrine.dbal.crm_connection');
            $stmt = $con->prepare('UPDATE vtiger_contactscf SET cf_851 = :password WHERE contactid = :contactid');
            $stmt->bindValue(':password', $newPassword);
            $stmt->bindValue(':contactid', $user->getId());

            $success = $stmt->execute();

            if ($success) {
                return JsonResponse::create(
                    [
                        'status' => $success ? 'success' : 'error',
                    ]
                );
            }
        }

        return JsonResponse::create(
            [
                'status' => 'wrong_password',
            ]
        );
    }
}
