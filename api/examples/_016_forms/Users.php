<?php

use Luracast\Restler\ArrayObject;
use Luracast\Restler\StaticProperties;
use Luracast\Restler\UI\FormStyles;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class Users
 * @response-format Html,Json
 */
class Users
{
    public function __construct(ServerRequestInterface $request, StaticProperties $html, StaticProperties $forms)
    {
        $bootstrap4 = [
            'cerulean',
            'cosmo',
            'cyborg',
            'darkly',
            'flatly',
            'journal',
            'litera',
            'lumen',
            'lux',
            'materia',
            'minty',
            'pulse',
            'sandstone',
            'simplex',
            'sketchy',
            'slate',
            'solar',
            'spacelab',
            'superhero',
            'united',
            'yeti',
        ];
        //bootstarp 3
        $bootstrap3 = [
            'cerulean',
            'cosmo',
            'cyborg',
            'darkly',
            'flatly',
            'journal',
            'lumen',
            'paper',
            'readable',
            'sandstone',
            'simplex',
            'slate',
            'solar',
            'spacelab',
            'superhero',
            'united',
            'yeti',
        ];
        $theme = $request->getQueryParams()['theme'] ?? $bootstrap3[array_rand($bootstrap3, 1)];
        $style = $theme == 'foundation5' ? 'foundation5' : 'bootstrap3';
        $html->data['theme'] = $theme;
        $html->data['themes'] = $bootstrap3;
        $html->data['style'] = $style;
        $forms->style = FormStyles::$$style;
    }

    function index()
    {
        return [];
    }

    /**
     * @return array {@label <span class="glyphicon glyphicon-user"></span> Sign In}
     */
    function postSignIn($email, $password)
    {
        return func_get_args();
    }

    /**
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     * @param string $password
     * @param Address $address
     *
     * @return array
     *
     * @view users
     */
    function postSignUp($firstName, $lastName, $email, $password, $address)
    {
        return func_get_args();
    }
}