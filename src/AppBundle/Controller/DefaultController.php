<?php

namespace AppBundle\Controller;

use AppBundle\Instagram\MayBeNeedAuthException;
use AppBundle\Instagram\UserNotFoundException;
use Guzzle\Service\Client;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render(
            'default/index.html.twig',
            array(
                'base_dir' => realpath($this->container->getParameter('kernel.root_dir').'/..'),
            )
        );
    }

    /**
     * @Route("/login", name="login")
     */
    public function loginAction(Request $request)
    {
        if ($this->get('session')->get('is_logged', false)) {
            return $this->redirectToRoute('homepage');
        }

        return $this->render(
            ':default:login.html.twig',
            array(
                'client_id'    => $this->getParameter('instagram.client_id'),
                'redirect_uri' => $this->generateUrl('instagram_redirect', array(), true),
                'is_logged'    => $this->get('session')->get('is_logged', false),
            )
        );
    }

    /**
     * @Route("/logout", name="logout")
     */
    public function logoutAction(Request $request)
    {
        if (!$this->get('session')->get('is_logged', false)) {
            return $this->redirectToRoute('login');
        }

        $this->get('session')->set('is_logged', false);

        $this->get('session')->set('instagram', array());


        return $this->redirectToRoute('homepage');
    }

    /**
     * @Route("/instagram", name="instagram_redirect")
     */
    public function instagramRedirectUri(Request $request)
    {
        $code = $request->get('code');

        if ($request->get('error')) {
            return $this->redirectToRoute('login');
        }

        $client = new Client('https://api.instagram.com/oauth/access_token');

        $request = $client->post()
            ->setPostField('client_id', $this->getParameter('instagram.client_id'))
            ->setPostField('client_secret', $this->getParameter('instagram.client_secret'))
            ->setPostField('grant_type', 'authorization_code')
            ->setPostField('redirect_uri', $this->generateUrl('instagram_redirect', array(), true))
            ->setPostField('code', $code);
        try {
            $response = $request->send();
        } catch (\Exception $e) {
            $this->addFlash('instagram_error', $e->getMessage());

            return $this->redirectToRoute('login');
        }

        $responseBodyArray = json_decode($response->getBody(true), true);

        $this->get('session')->set('instagram', $responseBodyArray);
        $this->get('session')->set('is_logged', true);

        return $this->redirectToRoute('homepage');
    }

    /**
     * @Route("/workspace", name="workspace")
     */
    public function workspaceAction(Request $request)
    {
        $formBuilder = $this->createFormBuilder();

        $form = $formBuilder
            ->add(
                'username',
                'text',
                array(
                    'label' => 'Instagram username',
                    'data'  => $this->get('session')->get('is_logged', false)
                        ? $this->get('session')->get('instagram')['user']['username']
                        : '',
                )
            )
            ->add(
                'count',
                'number',
                array(
                    'label'       => 'Count of images',
                    'data'        => 16,
                    'constraints' => array(
                        new NotBlank(),
                        new Range(
                            array(
                                'min' => 1,
                                'max' => 100,
                            )
                        ),
                    ),
                )
            )
            ->add('imagesOnly', 'checkbox', array('label' => 'Exclude videos?', 'data' => true))
            ->add(
                'from_media',
                'submit',
                array(
                    'label' => 'Make from media',
                    'attr'  => array('class' => 'f-bu f-bu-default'),
                )
            )
            ->add(
                'from_feed',
                'submit',
                array(
                    'label' => 'Make from feed',
                    'attr'  => array(
                        'class'    => 'f-bu f-bu-success',
                        'disabled' => !$this->get('session')->get('is_logged', false),
                    ),
                )
            )->getForm();

        $form->handleRequest($request);

        if ($form->isValid()) {
            $data = $form->getData();

            $source = $form->get('from_feed')->isClicked()
                ? 'feed'
                : 'media';

            return $this->redirectToRoute(
                'make_collage',
                array(
                    'count'      => $data['count'],
                    'source'     => $source,
                    'imagesOnly' => $data['imagesOnly'],
                    'username' => $data['username'],
                )
            );
        }

        return $this->render(
            ':default:workspace.html.twig',
            array(
                'form' => $form->createView(),
            )
        );
    }

    /**
     * @Route("/workspace/collage", name="make_collage")
     * @Method("GET")
     */
    public function makeCollage(Request $request)
    {
        $imRetriever = $this->get('instagram.media_retriever');

        //region Update options
        if (null !== $request->get('count', null)) {
            $imRetriever->setCount(intval($request->get('count')));
        }

        if (null !== $request->get('source', null)) {
            $imRetriever->setSource($request->get('source'));
        }

        if (null !== $request->get('imagesOnly', null)) {
            $imRetriever->setImagesOnly(!!intval($request->get('imagesOnly')));
        }

        if (null !== $request->get('username', null)) {
            try {
                $imRetriever->setUserId(
                    $this->get('instagram.user_retriever')->getUserId(
                        $request->get('username')
                    )
                );
            } catch (UserNotFoundException $e) {
                return $this->createNotFoundException();
            } catch (\Exception $e) {
            }
        } else {
            throw $this->createNotFoundException();
        }
        //endregion

        try {
            list($links, $isFullyRequested) = $imRetriever->getImageLinks();
        } catch (MayBeNeedAuthException $e) {
            $this->addFlash('try-to-auth', 'Looks like action need to auth with Instagram');

            return $this->redirectToRoute('login');
        } catch (\Exception $e) {
            var_dump($e);
        }

        if (false === $isFullyRequested) {
            $this->addFlash(
                'is-not-fully-requested-notice',
                'Count of images on medis less then requested count'
            );
        }

        return $this->render(
            ':default:makeCollage.html.twig',
            array(
                'links'    => $links,
                'username' => $request->get('username'),
            )
        );
    }
}
