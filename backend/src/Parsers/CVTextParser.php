<?php

declare(strict_types=1);

namespace Parsers;

class CVTextParser
{
    private string $text;

    public function __construct(string $cvText)
    {
        $this->text = $cvText;
    }

    public function parse(): array
    {
        return [
            'nombre' => $this->extractNombre(),
            'email' => $this->extractEmail(),
            'telefono' => $this->extractTelefono(),
            'linkedin' => $this->extractLinkedIn(),
            'direccion' => $this->extractDireccion(),
            'idiomas' => $this->extractIdiomas(),
            'experiencia' => $this->extractExperiencia(),
            'formacion' => $this->extractFormacion(),
            'skills' => $this->extractSkills(),
        ];
    }

    private function extractNombre(): string
    {
        preg_match("/(?:Nombre|Nombre completo)[:\s]+([A-Z\u00C0-\u017F][a-z\u00C0-\u017F]+(?: [A-Z][a-z]+)+)/u", $this->text, $matches);
        return $matches[1] ?? '';
    }

    private function extractEmail(): string
    {
        preg_match("/[\w._%+-]+@[\w.-]+\.[a-zA-Z]{2,6}/", $this->text, $matches);
        return $matches[0] ?? '';
    }

    private function extractTelefono(): string
    {
        preg_match("/(\+34)?[\s.-]?([6-9][0-9]{2})[\s.-]?([0-9]{3})[\s.-]?([0-9]{3})/", $this->text, $matches);
        return isset($matches[0]) ? trim($matches[0]) : '';
    }

    private function extractLinkedIn(): string
    {
        preg_match("/linkedin\.com\/in\/[\w\-]+/i", $this->text, $matches);
        return $matches[0] ?? '';
    }

    private function extractDireccion(): string
    {
        preg_match("/(?:Direcci[oó]n|Domicilio)[:\s]+(.+)/i", $this->text, $matches);
        return $matches[1] ?? '';
    }

    private function extractIdiomas(): array
    {
        preg_match("/(Idiomas|Lenguas)[\s:]*([\w\s,]+)/iu", $this->text, $matches);
        if (isset($matches[2])) {
            return array_map('trim', preg_split('/[,\n]/', $matches[2]));
        }
        return [];
    }

    private function extractExperiencia(): string
    {
        preg_match("/(?:Experiencia|Historial laboral|Trayectoria)[\s:]*([\s\S]{0,800})/iu", $this->text, $matches);
        return $matches[1] ?? '';
    }

    private function extractFormacion(): string
    {
        preg_match("/(?:Formaci[oó]n|Educaci[oó]n|Estudios)[\s:]*([\s\S]{0,600})/iu", $this->text, $matches);
        return $matches[1] ?? '';
    }

    private function extractSkills(): array
    {
        preg_match("/(?:Skills|Habilidades|Competencias)[\s:]*([\w\s,\n]+)/iu", $this->text, $matches);
        if (isset($matches[1])) {
            return array_map('trim', preg_split('/[,\n]/', $matches[1]));
        }
        return [];
    }
}
