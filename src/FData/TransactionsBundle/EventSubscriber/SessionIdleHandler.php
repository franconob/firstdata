<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 12/01/15
 * Time: 15:49
 */

namespace FData\TransactionsBundle\EventSubscriber;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\SecurityContextInterface;

class SessionIdleHandler
{

    protected $session;
    protected $securityContext;
    protected $router;
    protected $maxIdleTime;

    public function __construct(SessionInterface $session, SecurityContextInterface $securityContext, RouterInterface $router, $maxIdleTime = 0)
    {
        $this->session = $session;
        $this->securityContext = $securityContext;
        $this->router = $router;
        $this->maxIdleTime = $maxIdleTime;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST != $event->getRequestType()) {

            return;
        }
        if ($this->maxIdleTime > 0) {

            $this->session->start();
            $lapse = time() - $this->session->getMetadataBag()->getLastUsed();

            if ($lapse > $this->maxIdleTime) {

                $this->securityContext->setToken(null);
                //$this->session->getFlashBag()->set('info', 'You have been logged out due to inactivity.');

                // Change the route if you are not using FOSUserBundle.
                //var_dump($event->getRequest()->getMethod(), $event->getRequest()->headers->all());die;
                if ($event->getRequest()->isXmlHttpRequest()) {
                    $event->setResponse(JsonResponse::create([
                        'reason' => 'inactivity'
                    ], 403));
                } else {
                    $event->setResponse(new RedirectResponse($this->router->generate('f_data_security_homepage_extranet')));
                }
            }
        }
    }

}

