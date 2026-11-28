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
        // Recompila la plantilla si el archivo .tpl ha cambiado. Siempre activo:
        // si no, tras un "git pull" se seguirían viendo las páginas viejas
        // (la caché de plantillas vive en un volumen que sobrevive a reconstrucciones).
        // Cuesta solo mirar la fecha del archivo, inapreciable.
        $this->smarty->setCompileCheck(Smarty::COMPILECHECK_ON);
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
