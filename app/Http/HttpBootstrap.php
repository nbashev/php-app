<?php

namespace App\Http;

use App\Resources\HttpRoutes;
use DebugBar\JavascriptRenderer;
use DebugBar\StandardDebugBar;
use Greg\Framework\Http\BootstrapAbstract;
use Greg\Support\Http\Request;
use Greg\Support\Http\Response;

class HttpBootstrap extends BootstrapAbstract
{
    public function bootFixUriPath()
    {
        $path = Request::relativeUriPath();

        if (strlen($path) > 1 and substr($path, -1, 1) === '/') {
            Response::sendLocation(rtrim($path, '/'), 301);

            die;
        }
    }

    public function bootRoutes()
    {
        $this->app()->listen(HttpKernel::EVENT_RUN, function () {
            $this->ioc()->load(HttpRoutes::class);
        });
    }

    public function bootDebugBar()
    {
        $this->ioc()->inject(StandardDebugBar::class, function () {
            return new StandardDebugBar();
        });

        $this->ioc()->inject(JavascriptRenderer::class, function (StandardDebugBar $bar) {
            return $bar->getJavascriptRenderer('/debug');
        });

        $this->app()->listen(HttpKernel::EVENT_FINISHED, function (Response $response, JavascriptRenderer $renderer) {
            if ($this->app()['debug'] and !Request::isAjax() and $response->isHtml()) {
                $response->setContent(
                    $response->getContent() . '<span></span>' . $renderer->renderHead() . $renderer->render()
                );
            }
        });
    }
}
