<?php declare(strict_types=1);
namespace Annotate\Media\Ingester;

// use Annotate\Form\AnnotationBodyForm;
use Annotate\Form\AnnotateForm as AnnotationBodyForm;
use Laminas\ServiceManager\ServiceLocatorInterface as FormElementManager;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Request;
use Omeka\Entity\Media;
use Omeka\Entity\User;
use Omeka\File\Downloader;
// use Omeka\Media\Ingester\MutableIngesterInterface;
use Omeka\File\Uploader;
use Omeka\File\Validator;
use Omeka\Media\Ingester\IngesterInterface;
use Omeka\Stdlib\ErrorStore;

// class AnnotationBody implements MutableIngesterInterface
class AnnotationBody implements IngesterInterface
{
    /**
     * @var Uploader
     */
    protected $uploader;

    /**
     * @var Downloader
     */
    protected $downloader;

    /**
     * @var Validator
     */
    protected $validator;

    /**
     * @var FormElementManager
     */
    protected $formElementManager;

    /**
     * @var User
     */
    protected $user;

    public function __construct(
        Uploader $uploader,
        Downloader $downloader,
        Validator $validator,
        FormElementManager $formElementManager,
        User $user
    ) {
        $this->uploader = $uploader;
        $this->downloader = $downloader;
        $this->validator = $validator;
        $this->formElementManager = $formElementManager;
        $this->user = $user;
    }

    public function getLabel()
    {
        return 'Annotation body'; // @translate
    }

    public function getRenderer()
    {
        return 'annotation_body';
    }

    public function form(PhpRenderer $view, array $options = [])
    {
        return $this->getForm($view, $options);
    }

    // public function updateForm(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    // {
    //     return $this->getForm($view, $options);
    // }

    public function ingest(Media $media, Request $request, ErrorStore $errorStore): void
    {
        // All data are standard properties, so nothing to do.
    }

    // public function update(Media $media, Request $request, ErrorStore $errorStore)
    // {
    //     $data = $request->getContent();
    //     if (isset($data['o:media']['__index__']['annotation_body'])) {
    //         $value = $data['o:media']['__index__']['annotation_body'];
    //         $media->setData(['annotation_body' => $value]);
    //     }
    // }

    public function getForm(PhpRenderer $view, array $options = [])
    {
        $data = $options;

        $data['o:media[__index__][rdf:value][0][@value]'] = $options['rdf:value'][0]['@value'] ?? '';
        $data['o:media[__index__][oa:hasPurpose][0][@value]'] = $options['oa:hasPurpose'][0]['@value'] ?? '';
        $data['o:media[__index__][dcterms:format][0][@value]'] = $options['dcterms:format'][0]['@value'] ?? '';

        $data['o:media[__index__][dcterms:creator][0][@value]'] = $options['dcterms:creator'][0]['@value']
            ?? $this->user->getEmail();
        $data['o:media[__index__][dcterms:created][0][@value]'] = $options['dcterms:created'][0]['@value']
            ?? gmdate("Y-m-d\TH:i:s\Z");

        $form = $this->formElementManager->get(AnnotationBodyForm::class);
        $form->setData($data);
        return $view->formCollection($form);
    }
}
