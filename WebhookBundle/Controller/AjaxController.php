<?php

namespace Mautic\WebhookBundle\Controller;

use Mautic\CoreBundle\Controller\AjaxController as CommonAjaxController;
use Mautic\CoreBundle\Helper\InputHelper;
use Symfony\Component\HttpFoundation\Request;
use Mautic\IntegrationsBundle\Exception\IntegrationNotFoundException;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\WebhookBundle\Entity\Webhook;
use Mautic\WebhookBundle\Http\Client;
use Mautic\WebhookBundle\utils\RequestResponseProcessing;
use MauticPlugin\WHPBundle\Integration\WHPIntegration;


class AjaxController extends CommonAjaxController
{
    protected function sendHookTestAction(Request $request)
    {
        $url = InputHelper::url($request->request->get('url'));

        // validate the URL
        if ('' == $url || !$url) {
            // default to an error message
            $dataArray = [
                'success' => 1,
                'html'    => '<div class="has-error"><span class="help-block">'
                    .$this->translator->trans('mautic.webhook.label.no.url')
                    .'</span></div>',
            ];

            return $this->sendJsonResponse($dataArray);
        }

        // get the selected types
        $selectedTypes = InputHelper::cleanArray($request->request->get('types'));
        $payloadPaths  = $this->getPayloadPaths($selectedTypes);
        $payloads      = $this->loadPayloads($payloadPaths);
        $now           = new \DateTime();

        $payloads['timestamp'] = $now->format('c');

        // set the response
        /** @var Psr\Http\Message\ResponseInterface $response */
        $response = $this->get('mautic.webhook.http.client')->post($url, $payloads, InputHelper::string($request->request->get('secret')));

        // default to an error message
        $dataArray = [
            'success' => 1,
            'html'    => '<div class="has-error"><span class="help-block">'
                .$this->translator->trans('mautic.webhook.label.warning')
                .'</span></div>',
        ];

        // if we get a 2xx response convert to success message
        if (2 == substr($response->getStatusCode(), 0, 1)) {
            $dataArray['html'] =
                '<div class="has-success"><span class="help-block">'
                .$this->translator->trans('mautic.webhook.label.success')
                .'</span></div>';
        }

        return $this->sendJsonResponse($dataArray);
    }

    /*
     * Get an array of all the payload paths we need to load
     *
     * @param $types array
     * @return array
     */
    public function getPayloadPaths($types)
    {
        $payloadPaths = [];

        foreach ($types as $type) {
            // takes an input like mautic.lead_on_something
            // converts to array pieces using _
            $typePath = explode('_', $type);

            // pull the prefix into its own variable
            $prefix = $typePath[0];

            // now that we have the remove it from the array
            unset($typePath[0]);

            // build the event name by putting the pieces back together
            $eventName = implode('_', $typePath);

            // default the path to core
            $payloadPath = $this->factory->getSystemPath('bundles', true);

            // if plugin is in first part of the string this is an addon
            // input is plugin.bundlename or mautic.bundlename
            if (strpos('plugin.', $prefix)) {
                $payloadPath = $this->factory->getSystemPath('plugins', true);
            }

            $prefixParts = explode('.', $prefix);

            $bundleName = (array_pop($prefixParts));

            $payloadPath .= '/'.ucfirst($bundleName).'Bundle/Assets/WebhookPayload/'.$bundleName.'_'.$eventName.'.json';

            $payloadPaths[$type] = $payloadPath;
        }

        return $payloadPaths;
    }

    /*
     * Iterate through the paths and get the json payloads
     *
     * @param  $paths array
     * @return $payload array
     */
    public function loadPayloads($paths)
    {
        $payloads = [];

        foreach ($paths as $key => $path) {
            if (file_exists($path)) {
                $payloads[$key] = json_decode(file_get_contents($path), true);
            }
        }

        return $payloads;
    }


    //    CUSTOM
    public function sendHookTestPremAction(Request $request)
    {
        $url = InputHelper::url($request->request->get('url'));

        // validate the URL
        if ('' == $url || !$url) {
            // default to an error message
            $dataArray = [
                'success' => 1,
                'html'    => '<div class="has-error"><span class="help-block">'
                    .$this->translator->trans('mautic.webhook.label.no.url')
                    .'</span></div>',
            ];

            return $this->sendJsonResponse($dataArray);
        }

        // get the selected types
        $selectedTypes = InputHelper::cleanArray($request->request->get('types'));
        $payloadPaths  = $this->getPayloadPaths($selectedTypes);
        $payloads      = $this->loadPayloads($payloadPaths);
        $now           = new \DateTime();

        # GET DATA
        $extra = $request->get('extra', '');
        $url = $request->get('url', '');
        $method = $request->get('method', '');
        $headers = $request->get('headers', '');
        $auth_type = $request->get('authType', '');
        $login = $request->get('login', '');
        $password = $request->get('password', '');
        $token = $request->get('token', '');
        $subject_entity_id = $request->get('testContactId', '');
        $actualLoad = $request->get('actualLoad', '');
//        $fieldsWithValues = $request->get('fieldsWithValues');
        $subject = $request->get('subject');

        // parse $receivedFields in form
        $forms = $_POST['form'];
        $receivedFields = [];
        $subjectFields = [];
        foreach ($forms as $form){
            if ($this->endsWith($form['name'], '][receivedField]')){
                $receivedFields[] = $form['value'];
            }
            if ($this->endsWith($form['name'], '][subjectField]')){
                $subjectFields[] = $form['value'];
            }
        }
        $test = array_combine( $receivedFields, $subjectFields );

        // parse $headers in form
        $forms = $_POST['form'];
        $receivedFields = [];
        $subjectFields = [];
        foreach ($forms as $form){
            if ($this->endsWith($form['name'], '][headerKey]')){
                $receivedFields[] = $form['value'];
            }
            if ($this->endsWith($form['name'], '][headerValue]')){
                $subjectFields[] = $form['value'];
            }
        }
        $headers = array_combine( $receivedFields, $subjectFields );

        // test $wh
        $whRepository = $this->factory->getEntityManager()->getRepository(Webhook::class);
        $wh = $whRepository->find(1);

        # PROCESSING DATA
        if($this->checkIfPremium()){
            $requestResponseProcessing = new RequestResponseProcessing(
                $url, $method, $headers, $auth_type, $login, $password, $token, $actualLoad,
//                $fieldsWithValues,
                $subject_entity_id, $this->factory, $extra, $subject, $payloads, $wh, $test
            );
            $responseList = $requestResponseProcessing->startProcessing();
            $response = $responseList[0];
            $responseData = $responseList[1];
        }else{
            $response = false;
        }

        # CREATE RESPONSE DATA
        if($response){
            $dataArray = [
                'success' => 1,
                'html'    => '<div><span class="help-block">'
                    .$this->translator->trans('mautic.webhook.label.prem.success')
                    .'</span></div>',
            ];
        }
        else{
            $dataArray = [
                'success' => 0,
                'html'    => '<div><span class="help-block">'
                    .$this->translator->trans('mautic.webhook.label.prem.error')
                    .'</span></div>',
            ];
        }
        # RETURN RESPONSE
        return $this->sendJsonResponse($dataArray);
    }
    //    CUSTOM

    public function checkIfPremium()
    {
        $integrationHelper = $this->factory->getHelper('integration');
        $WHPIntegration = $integrationHelper->getIntegrationObject(WHPIntegration::NAME);
        if($WHPIntegration) {
            try {
                $integration = $WHPIntegration->getIntegrationConfiguration();
                return $integration->getIsPublished() ?: false;
            } catch (IntegrationNotFoundException $e) {
                return false;
            }
        }
        return false;
    }

    public function endsWith($haystack, $needle) {
        $length = strlen( $needle );
        if( !$length ) {
            return true;
        }
        $res = substr( $haystack, -$length ) === $needle;
        return $res;
    }
}
