<?php

namespace SEF;
include_once "common.php";
include_once "replace.php";

abstract class Page implements RendableEntity {
    private $template;

    protected $params;
    protected $replace;

    public $title;
    public $description;
    public $keywords;

    public $content;

    function __construct(array $params) {
        $this->params = $params;

        $this->replace = new Replace($this);
        $this->replace->addVariable('Page\Title', 'title');
        $this->replace->addVariable('Page\Description', 'description');
        $this->replace->addVariable('Page\Keywords', 'keywords');
        $this->replace->addVariable("Template\Content", 'content');

        $this->template = "";
    }

    function render(): string {
        $this->template = $this->getTemplate();
        $this->content = $this->getContent();

        $content = $this->replace->replace($this->template);

        if(strpos($content, '{%%Template\\') !== false) {
            throw new \Exception('Page::render(): Template has unsolved values.');
        }
        if(strpos($content, '{$$Template\\') !== false) {
            throw new \Exception('Page::render(): Template has unsolved variables.');
        }
        if(strpos($content, '{&&Template\\') !== false) {
            throw new \Exception('Page::render(): Template has unsolved functions.');
        }

        return $content;
    }

    abstract function getTemplate(): string;
    abstract function getContent(): string;
}

class BlankPage extends Page {
    function getTemplate(): string {
        return '{%%Template\Content%%}';
    }

    function getContent(): string {
        return  "";
    }
}
