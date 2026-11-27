<?php

declare(strict_types=1);

namespace App;

use Psr\Http\Message\ResponseInterface;
use Smarty\Smarty;

/**
 * Pequeño envoltorio de Smarty para pintar una plantilla
 * dentro de una respuesta de Slim.
 */
final class View
{
    private Smarty $smarty;

    public function __construct(array $config, bool $debug)
    {
        $this->smarty = new Smarty();
        $this->smarty->setTemplateDir($config['templates']);
        $this->smarty->setCompileDir($config['compile']);
        // Escapa TODAS las variables {$algo} por defecto (protección XSS).
        // Si alguna vez necesitas HTML sin escapar: {$algo nofilter}
        $this->smarty->setEscapeHtml(true);
        // En pruebas recompila al momento; en producción no comprueba cambios.
        $this->smarty->setCompileCheck($debug ? Smarty::COMPILECHECK_ON : Smarty::COMPILECHECK_OFF);
    }

    public function render(ResponseInterface $response, string $template, array $data = []): ResponseInterface
    {
        $tpl = $this->smarty->createTemplate($template);
        foreach ($data as $key => $value) {
            $tpl->assign($key, $value);
        }
        $response->getBody()->write($tpl->fetch());

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
