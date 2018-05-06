<?php
namespace Annotate\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Omeka\Entity\Resource;

/**
 * @Entity
 */
class Annotation extends Resource
{
    /**
     * @Id
     * @Column(type="integer")
     */
    protected $id;

    /**
     * @OneToMany(
     *     targetEntity="AnnotationTarget",
     *     mappedBy="annotation",
     *     orphanRemoval=true,
     *     cascade={"persist", "remove", "detach"},
     *     indexBy="id"
     * )
     * @OrderBy({"id" = "ASC"})
     */
    protected $targets;

    /**
     * @OneToMany(
     *     targetEntity="AnnotationBody",
     *     mappedBy="annotation",
     *     orphanRemoval=true,
     *     cascade={"persist", "remove", "detach"},
     *     indexBy="id"
     * )
     * @OrderBy({"id" = "ASC"})
     */
    protected $bodies;

    public function __construct()
    {
        parent::__construct();
        $this->targets = new ArrayCollection;
        $this->bodies = new ArrayCollection;
    }

    public function getResourceName()
    {
        return 'annotations';
    }

    public function getId()
    {
        return $this->id;
    }

    public function getTargets()
    {
        return $this->targets;
    }

    public function getBodies()
    {
        return $this->bodies;
    }
}
