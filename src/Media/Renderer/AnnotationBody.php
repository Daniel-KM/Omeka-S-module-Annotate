<?php declare(strict_types=1);

namespace Annotate\Media\Renderer;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\Renderer\RendererInterface;

class AnnotationBody implements RendererInterface
{
    /**
     * Render an annotation.
     *
     * @param PhpRenderer $view,
     * @param MediaRepresentation $media
     * @param array $options
     * @return string
     */
    public function render(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        return '';
    }
}
