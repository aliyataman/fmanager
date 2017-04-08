<?php

/* view.html */
class __TwigTemplate_9fe2ba8521146ebfc698f347d30814452762e9c6ab2ceb7a2feaa700c9684123 extends Twig_Template
{
    public function __construct(Twig_Environment $env)
    {
        parent::__construct($env);

        $this->parent = false;

        $this->blocks = array(
        );
    }

    protected function doDisplay(array $context, array $blocks = array())
    {
        // line 1
        echo "Merhaba ";
        echo twig_escape_filter($this->env, ($context["name"] ?? null), "html", null, true);
    }

    public function getTemplateName()
    {
        return "view.html";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  19 => 1,);
    }

    public function getSourceContext()
    {
        return new Twig_Source("", "view.html", "/Users/fatih/Desktop/siyahmadde/file-manager/templates/view.html");
    }
}
