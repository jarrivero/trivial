<?php

declare(strict_types=1);

namespace App;

use Psr\Http\Message\ResponseInterface;
use Smarty\Smarty;

/**
 * Small Smarty wrapper to render a template into a Slim response.
 */
final class View
{
    private Smarty $smarty;

    public function __construct(array $config, bool $debug)
    {
        $this->smarty = new Smarty();
        $this->smarty->setTemplateDir($config['templates']);
        $this->smarty->setCompileDir($config['compile']);
        // Escape ALL {$variables} by default (XSS protection).
        // If you ever need raw HTML: {$variable nofilter}
        $this->smarty->setEscapeHtml(true);
        // Recompile a template when its .tpl file changes. Always on: otherwise,
        // after a "git pull" the old pages would still be served (the compiled
        // templates live in a volume that survives rebuilds).
        // It only costs a file date check, negligible.
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
