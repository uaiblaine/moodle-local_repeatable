<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'local_repeatable'.
 *
 * @package    local_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['errorcodeinactive'] = 'Conjunto {$a->set}: o código "{$a->code}" de {$a->column} não é mais oferecido.';
$string['errorcodeparent'] = 'Conjunto {$a->set}: {$a->column} "{$a->code}" não pertence a {$a->parent} "{$a->parentcode}".';
$string['errorcodeparentmissing'] = 'Conjunto {$a->set}: escolha um valor para {$a->parent} antes de {$a->column}.';
$string['errorcoderestricted'] = 'Conjunto {$a->set}: {$a->column} é uma lista restrita. Escolha um valor definido por um administrador ou peça acesso.';
$string['errorcodeunknown'] = 'Conjunto {$a->set}: "{$a->code}" não é um código de {$a->column}.';
$string['errorcolumnrequired'] = 'Conjunto {$a->set}: {$a->column} é obrigatório.';
$string['errorcolumnslimit'] = 'Um campo pode ter no máximo {$a} colunas.';
$string['errorcolumnsrequired'] = 'Adicione pelo menos uma coluna.';
$string['errordomaininvalid'] = 'Coluna "{$a}": escolha um dicionário (letras minúsculas, dígitos e sublinhado).';
$string['errordomainmissing'] = 'Dicionário não encontrado no local_dictionaries: {$a}.';
$string['errorinvalidjson'] = 'Payload repetível inválido; esperado um objeto JSON.';
$string['errorkeyconflict'] = 'Duas colunas geram a coluna de relatório "{$a}": mude uma das chaves.';
$string['errorkeyduplicate'] = 'Chave usada por duas colunas: {$a}.';
$string['errorkeyinvalid'] =
    'Chave inválida "{$a}": comece com uma letra minúscula, seguida de até 39 letras minúsculas, dígitos ou sublinhados.';
$string['errorkeylocked'] = 'Colunas com valores armazenados não podem ser removidas nem ter a chave alterada: {$a}.';
$string['errorlabelduplicate'] = 'Nome de coluna usado duas vezes: {$a}.';
$string['errorlabelrequired'] = 'A coluna {$a} não tem nome.';
$string['errormodeinvalid'] = 'Coluna "{$a}": escolha texto livre ou um modo de dicionário.';
$string['errorparentinvalid'] = 'A coluna "{$a}" só pode depender de uma coluna de dicionário anterior.';
$string['errorparentmismatch'] = 'Coluna "{$a->column}": a coluna pai não usa "{$a->parentdomain}", o dicionário que contém os pais de "{$a->domain}".';
$string['errorpattern'] = 'Conjunto {$a->set}, {$a->column}: {$a->error}';
$string['errorpatternconfig'] = 'Coluna "{$a->column}": {$a->error}';
$string['errorpatternunavailable'] =
    'Coluna "{$a}": colunas de padrão precisam do plugin customfield_pattern (Moodle 5.1 ou posterior). Sem ele, uma coluna de padrão já salva é mantida sem alterações e nenhuma nova pode ser adicionada.';
$string['errorschemainvalid'] = 'As colunas não formam um esquema versão 2 válido.';
$string['errorsublinelimit'] = 'No máximo {$a} colunas podem aparecer na linha auxiliar.';
$string['errorsublinetitle'] = 'A coluna "{$a}" é o título e não pode estar também na linha auxiliar.';
$string['errortitlecount'] = 'Marque exatamente uma coluna como título do conjunto.';
$string['errortoomanysets'] = 'Conjuntos demais: no máximo {$a} conjuntos são permitidos.';
$string['errorvaluetoolong'] = 'Valor muito longo: cada valor é limitado a {$a} caracteres.';
$string['pluginname'] = 'Motor de conjuntos repetíveis';
$string['privacy:metadata'] = 'O motor de conjuntos repetíveis não armazena dados pessoais: cada plugin que o usa armazena, exporta e apaga os seus.';
$string['repeatableset'] = 'Conjunto {$a}';
