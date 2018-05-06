<?php
namespace Annotate\Media\Renderer;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\Renderer\RendererInterface;
use Zend\View\Renderer\PhpRenderer;

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
