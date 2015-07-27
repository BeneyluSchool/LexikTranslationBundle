<?php

namespace Lexik\Bundle\TranslationBundle\Controller;

use Lexik\Bundle\TranslationBundle\Command\ExportTranslationsCommand;
use Lexik\Bundle\TranslationBundle\Propel\TransUnitQuery;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Cédric Girard <c.girard@lexik.fr>
 */
class TranslationController extends Controller
{
    /**
     * Display the translation grid.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function gridAction()
    {
        return $this->render('LexikTranslationBundle:Translation:grid.html.twig', array(
            'layout'        => $this->container->getParameter('lexik_translation.base_layout'),
            'inputType'     => $this->container->getParameter('lexik_translation.grid_input_type'),
            'toggleSimilar' => $this->container->getParameter('lexik_translation.grid_toggle_similar'),
            'locales'       => $this->getManagedLocales()
        ));
    }

    /**
     * Display the translation grid.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function domainsAction()
    {
        return $this->render('LexikTranslationBundle:Domains:domains_management.html.twig', array(
            'layout'        => $this->container->getParameter('lexik_translation.base_layout'),
            'domains'       => $this->getCurrentDomains()
        ));
    }

    /**
     * Remove cache files for managed locales.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function invalidateCacheAction(Request $request)
    {
        $this->get('translator')->removeLocalesCacheFiles($this->getManagedLocales());

        $message = $this->get('translator')->trans('translations.cache_removed', array(), 'LexikTranslationBundle');

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(array('message' => $message));
        }

        $this->get('session')->getFlashBag()->add('success', $message);

        return $this->redirect($this->generateUrl('lexik_translation_grid'));
    }

    /**
     * Add a new trans unit with translation for managed locales.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function newAction(Request $request)
    {
        $handler = $this->get('lexik_translation.form.handler.trans_unit');

        $form = $this->createForm('lxk_trans_unit', $handler->createFormData(), $handler->getFormOptions());

        if ($handler->process($form, $request)) {

            $message = $this->get('translator')->trans('translations.succesfully_added', array(), 'LexikTranslationBundle');

            $this->get('session')->getFlashBag()->add('success', $message);

            $redirectUrl = $form->get('save_add')->isClicked() ? 'lexik_translation_new' : 'lexik_translation_grid';

            return $this->redirect($this->generateUrl($redirectUrl));
        }

        return $this->render('LexikTranslationBundle:Translation:new.html.twig', array(
            'layout' => $this->container->getParameter('lexik_translation.base_layout'),
            'form'   => $form->createView(),
        ));
    }

    /**
     * Update trads.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function refreshAction(Request $request)
    {
        $command = new ExportTranslationsCommand();
        $command->setContainer($this->container);
        $input = new ArrayInput(array());
        $output = new NullOutput();
        $resultCode = $command->run($input, $output);

		$this->get('translator')->removeLocalesCacheFiles($this->getManagedLocales());

        $message = $this->get('translator')->trans('translations.cache_removed', array(), 'LexikTranslationBundle');

		apc_clear_cache();

        $this->get('session')->getFlashBag()->add('success', 'Trads à jour, bravo !');

        return $this->redirect($this->generateUrl('lexik_translation_grid'));
    }

    /**
     * Returns managed locales.
     *
     * @return array
     */
    protected function getManagedLocales()
    {
        return $this->container->getParameter('lexik_translation.managed_locales');
    }

    /**
     * Get all current domains .
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function getCurrentDomains(){

        $domains = TransUnitQuery::create()
            ->select('domain')
            ->groupBy('domain')
            ->find();

        return $domains;
    }

    /**
     * Validate a translation .
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function validateAction($id)
    {
        TransUnitQuery::create()
            ->filterById($id)
            ->update(array('status' => '3'));

        $this->get('session')->getFlashBag()->add('success', 'Traduction validée !');
        return $this->redirect($this->generateUrl('lexik_translation_grid'));
    }

    /**
     * Set a translation's status to "Waiting".
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function waitingAction($id)
    {
        TransUnitQuery::create()
            ->filterById($id)
            ->update(array('status' => '2'));

        $this->get('session')->getFlashBag()->add('success', 'Traduction mise en attente !');
        return $this->redirect($this->generateUrl('lexik_translation_grid'));
    }

    /**
     * Invalidate a translation .
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function invalidateAction($id)
    {
        TransUnitQuery::create()
            ->filterById($id)
            ->update(array('status' => '1'));

        $this->get('session')->getFlashBag()->add('success', 'Traduction révoquée !');
        return $this->redirect($this->generateUrl('lexik_translation_grid'));
    }


    /**
     * Validate every translation from a domain .
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function validateDomainAction($domain = 'messages')
    {
        TransUnitQuery::create()
            ->filterByDomain($domain)
            ->update(array('status' => '3'));

        $message = $this->get('translator')->trans('translations.domain_valid', array(), 'LexikTranslationBundle');
        $this->get('session')->getFlashBag()->add('success', $message);

        return $this->redirect($this->generateUrl('lexik_translation_domains'));

    }

    /**
     * Change status of every translation from a domain to "waiting".
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function waitingDomainAction($domain = 'messages')
    {
        TransUnitQuery::create()
            ->filterByDomain($domain)
            ->update(array('status' => '2'));

        return $this->redirect($this->generateUrl('lexik_translation_domains'));

    }

    /**
     * Invalidate every translation from a domain .
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function invalidateDomainAction(Request $request, $domain = 'messages')
    {
        TransUnitQuery::create()
            ->filterByDomain($domain)
            ->update(array('status' => '1'));

        $message = $this->get('translator')->trans('translations.domain_invalid', array(), 'LexikTranslationBundle');
        $this->get('session')->getFlashBag()->add('success', $message);

        return $this->redirect($this->generateUrl('lexik_translation_domains'));
    }
}
