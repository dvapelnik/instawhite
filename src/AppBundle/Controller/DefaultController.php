<?php

namespace AppBundle\Controller;

use AppBundle\Instagram\AccessDeniedException;
use AppBundle\Instagram\CollageMaker;
use AppBundle\Instagram\MayBeNeedAuthException;
use AppBundle\Instagram\UserNotFoundException;
use Guzzle\Service\Client;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Range;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        return $this->redirectToRoute('workspace');
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


        return $this->redirectToRoute('workspace');
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

        if (null !== ($redirectUrl = $this->get('session')->get('auth-redirect', null))) {
            $this->get('session')->remove('auth-redirect');

            return $this->redirect($redirectUrl);
        } else {
            return $this->redirectToRoute('workspace');
        }
    }

    /**
     * @Route("/workspace", name="workspace")
     */
    public function workspaceAction(Request $request)
    {
        $formBuilder = $this->createFormBuilder();

        //region Build form
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
                    'attr'        => array(
                        'placeholder' => 16,
                    ),
                    'required'    => false,
                    'constraints' => array(
                        new Range(
                            array(
                                'min' => 1,
                                'max' => 300,
                            )
                        ),
                    ),
                )
            )
            ->add(
                'pattern',
                'choice',
                array(
                    'choices' => array('grid' => 'Grid', 'random' => 'Random'),
                    'label'   => 'Select fill type',
                )
            )
            ->add(
                'palette',
                null,
                array(
                    'label'    => 'Select palette if you want',
                    'attr'     => array(
                        'placeholder' => 'Click here!',
                    ),
                    'required' => false,
                )
            )
            ->add(
                'usePalette',
                'checkbox',
                array
                (
                    'label'    => 'Filter by selected palette?',
                    'data'     => false,
                    'required' => false,
                )
            )
            ->add(
                'colorDelta',
                'number',
                array(
                    'label' => 'Select color delta (optimal is 150)',
                    'data'  => 150,
                )
            )
            ->add(
                'imagesOnly',
                'checkbox',
                array(
                    'label'    => 'Exclude videos?',
                    'required' => false,
                    'data'     => true,
                )
            )
            ->add(
                'size',
                'number',
                array(
                    'label'       => 'Size (px)',
                    'attr'        => array(
                        'placeholder' => 800,
                    ),
                    'required'    => false,
                    'constraints' => array(
                        new Range(
                            array(
                                'min' => 100,
                                'max' => 1024,
                            )
                        ),
                    ),
                )
            )
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
                    'label' => 'Make from own feed',
                    'attr'  => array(
                        'class' => 'f-bu f-bu-success',
//                        'disabled' => !$this->get('session')->get('is_logged', false),
                    ),
                )
            )->getForm();
        //endregion

        $form->handleRequest($request);

        if ($form->isValid()) {
            $data = $form->getData();

            $source = $form->get('from_feed')->isClicked()
                ? 'feed'
                : 'media';

            if (!isset($data['count']) && !isset($data['size'])) {
                $form->addError(new FormError("One of 'Count' or 'Size' should be defined"));
            } elseif ($source === 'feed' && !$this->get('session')->get('is_logged', false)) {
                $form->addError(new FormError('You are not authorized with Instagram for use own feed. Use login'));
            } else {
                $redirectDataArray = array(
                    'count'      => $data['count'],
                    'source'     => $source,
                    'imagesOnly' => $data['imagesOnly'],
                    'palette'    => $data['palette'],
                    'usePalette' => $data['usePalette'],
                    'username'   => $data['username'],
                    'colorDelta' => $data['colorDelta'],
                    'size'       => $data['size'],
                    'pattern'    => $data['pattern'],
                );

                if (isset($data['size'])) {
                    $redirectDataArray['size'] = $data['size'];
                }

                return $this->redirectToRoute(
                    'make_collage',
                    $redirectDataArray
                );
            }
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

        try {
            $userApiData = $this->get('instagram.user_retriever')->getUserData($request->get('username'));
        } catch (UserNotFoundException $e) {
            return $this->redirectToRoute(
                'instagram_user_not_found',
                array(
                    'username' => $request->get('username'),
                )
            );
        }

        list($size, $count) = $imRetriever->defineSizeAndCount(
            $request->get('size', null),
            $request->get('count', null)
        );

        $imRetriever->setCount($count);

        if (null !== ($source = $request->get('source', null))) {
            $imRetriever->setSource($source);
        }

        if (null !== ($imagesOnly = $request->get('imagesOnly', null))) {
            $imRetriever->setImagesOnly(!!intval($imagesOnly));
        }

        if (null !== ($username = $request->get('username', null))) {
            try {
                $imRetriever->setUserId(
                    $this->get('instagram.user_retriever')->getUserId($username)
                );
            } catch (UserNotFoundException $e) {
                throw $this->createNotFoundException();
            } catch (\Exception $e) {
            }
        } else {
            throw $this->createNotFoundException();
        }

        if (null !== ($palette = $request->get('palette'))) {
            $imRetriever->setPalette(
                $this->get('instagram.image_comparator')->hex2rgb($palette)
            );
        }

        if (null !== ($usePalette = $request->get('usePalette', null))) {
            $imRetriever->setUsePalette(!!intval($usePalette));
        }

        if (null !== ($colorDiffDelta = $request->get('colorDelta', null))) {
            $imRetriever->setColorDiffDelta(intval($colorDiffDelta));
        }
        //endregion

        try {
            $images = $imRetriever->getImageLinks();
        } catch (MayBeNeedAuthException $e) {
            $this->addFlash('try-to-auth', 'Looks like action need to auth with Instagram');

            $this->get('session')->set('auth-redirect', $request->getUri());

            return $this->redirectToRoute('login');
        } catch (AccessDeniedException $e) {
            return $this->render(
                ':default:accessDenied.html.twig',
                array(
                    'userApiData' => $userApiData,
                )
            );
        } catch (\Exception $e) {
            $this->get('logger')->warning(
                'Exception',
                array(
                    'message' => $e->getMessage(),
                    'trace'   => $e->getTrace(),
                )
            );
        }

        if (count($images) < $imRetriever->getCount()) {
            $this->addFlash(
                'is-not-fully-requested-notice',
                'Count of images on media less then requested count'
            );
        }

        $collageHashKey = count($images) > 0 ?
            md5(
                serialize(
                    array(
                        array(
                            'size'    => $size,
                            'images'  => $images,
                            'pattern' => $request->get('pattern', 'grid'),
                        ),
                    )
                )
            ) : false;

        if (!file_exists(
            $collageFileName = implode(
                DIRECTORY_SEPARATOR,
                array(
                    $this->get('kernel')->getRootDir(),
                    '..',
                    'web',
                    $this->getParameter('instagram.collage_save_path'),
                    $collageHashKey.'.png',
                )
            )
        )
        ) {
            if ($collageHashKey) {
                $collageMaker = new CollageMaker(
                    $size,
                    $images,
                    $this->get('instagram.filler_factory')->makeFiller($request->get('pattern', 'grid'))
                );
                $imagickCollage = $collageMaker->makeCollage();

                $imagickCollage->writeImage($collageFileName);
            }
        }

        return $this->render(
            ':default:makeCollage.html.twig',
            array(
                'links'          => $images,
                'imagesIsFound'  => count($images) > 0,
                'user'           => $request->get('source') === 'media'
                    ? $userApiData
                    : $this->get('session')->get('instagram')['user'],
                'palette'        => $imRetriever->isUsePalette() && $request->get('palette', null) !== null
                    ? '#'.$request->get('palette', null)
                    : false,
                'sourceSuffix'   => $imRetriever->getSource() === 'feed'
                    ? 'own feed'
                    : 'recent media',
                'collageHashKey' => $collageHashKey,
            )
        );
    }

    /**
     * @Route("/workspace/collage/image/{hash}", name="generate_collage")
     * @Method("GET")
     */
    public function generateCollage($hash)
    {
        $collageData = $this->get('cross_request_session_proxy')->getObject($hash);

        $collageMaker = new CollageMaker(
            $collageData['size'],
            $collageData['images'],
            $this->get('instagram.filler_factory')->makeFiller($collageData['pattern'])
        );

        $imagickCanvas = $collageMaker->makeCollage();

        $response = new Response($imagickCanvas);
        $response->headers->set('Content-Type', 'image/png');

        return $response;
    }

    /**
     * @Route("/user-not-found/{username}", name="instagram_user_not_found")
     * @Method("GET")
     */
    public function userNotFound($username)
    {
        return $this->render(
            'default/userNotFound.html.twig',
            array(
                'username' => $username,
            )
        );
    }
}
