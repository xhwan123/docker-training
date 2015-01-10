<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    /**
     * @Route("/redis/set-value", name="redis_set_value")
     */
    public function indexAction()
    {
        $predis = $this->get('snc_redis.default');
        $predis->incr('test');
        return $this->render(':default:redis-set.html.twig');
    }

    /**
     * @Route("/redis/get-value")
     */
    public function getValueAction()
    {
        $predis = $this->get('snc_redis.default');

        return $this->render(
            ':default:redis-get.html.twig',
            [
                'value' => $predis->get('test')
            ]
        );
    }
}
