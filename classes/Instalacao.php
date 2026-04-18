<?php
require_once __DIR__ . '/Ativo.php';

/**
 * Base para ativos fixos: pátio, oficina, escritório (tabelas patios, oficinas, escritorios).
 */
abstract class Instalacao extends Ativo
{
    /** @var string Nome da tabela (ex.: patios) */
    protected $tabela = '';

    /**
     * Valida e normaliza a tag: vazia ou apenas letras (A–Z, a–z) e números, até 50 caracteres.
     *
     * @return string|null tag ou null se vazia
     */
    protected function validarTag($dados)
    {
        $tag = trim((string) ($dados['tag'] ?? ''));
        if ($tag === '') {
            return null;
        }
        if (strlen($tag) > 50) {
            throw new Exception('A tag deve ter no máximo 50 caracteres.');
        }
        if (!preg_match('/^[A-Za-z0-9]+$/', $tag)) {
            throw new Exception('A tag deve conter apenas letras e números, sem espaços ou símbolos.');
        }

        return $tag;
    }

    /**
     * Área em m²: opcional; se informada, apenas dígitos e um separador decimal (vírgula ou ponto),
     * até 10 dígitos na parte inteira e no máximo 2 casas decimais (compatível com DECIMAL(12,2)).
     *
     * @return float|null
     */
    protected function validarAreaM2($dados)
    {
        $raw = trim((string) ($dados['area_m2'] ?? ''));
        if ($raw === '') {
            return null;
        }
        $raw = str_replace(' ', '', $raw);
        if (preg_match('/[^\d.,]/', $raw)) {
            throw new Exception('A área deve conter apenas números e um separador decimal (vírgula ou ponto).');
        }
        if (substr_count($raw, '.') + substr_count($raw, ',') > 1) {
            throw new Exception('Use no máximo um separador decimal na área (m²).');
        }
        $normalized = str_replace(',', '.', $raw);
        if ($normalized === '.' || $normalized === '') {
            throw new Exception('Informe um valor numérico válido para a área.');
        }
        if (strpos($normalized, '.') === 0) {
            $normalized = '0' . $normalized;
        }
        if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $normalized)) {
            throw new Exception('A área deve ter até 10 dígitos inteiros e no máximo duas casas decimais (ex.: 123 ou 12,5).');
        }
        $val = (float) $normalized;
        if ($val < 0) {
            throw new Exception('A área não pode ser negativa.');
        }
        if ($val > 9999999999.99) {
            throw new Exception('A área excede o valor máximo permitido.');
        }

        return round($val, 2);
    }

    public function listar()
    {
        $sql = "SELECT * FROM `{$this->tabela}` ORDER BY nome";
        return $this->db->query($sql);
    }

    public function buscarPorId($id)
    {
        $sql = "SELECT * FROM `{$this->tabela}` WHERE id = :id";
        $result = $this->db->query($sql, [':id' => $id]);
        return $result ? $result[0] : null;
    }

    public function cadastrar($dados)
    {
        $tag = $this->validarTag($dados);
        if ($tag !== null) {
            $sql = "SELECT id FROM `{$this->tabela}` WHERE tag = :tag";
            $result = $this->db->query($sql, [':tag' => $tag]);
            if ($result) {
                throw new Exception('Já existe um registro com esta tag.');
            }
        }

        $sql = "INSERT INTO `{$this->tabela}` (
                tag, nome, localizacao, area_m2, observacoes, foto, status
            ) VALUES (
                :tag, :nome, :localizacao, :area_m2, :observacoes, :foto, :status
            )";

        $params = [
            ':tag' => $tag,
            ':nome' => trim($dados['nome'] ?? ''),
            ':localizacao' => trim($dados['localizacao'] ?? '') ?: null,
            ':area_m2' => $this->validarAreaM2($dados),
            ':observacoes' => trim($dados['observacoes'] ?? '') ?: null,
            ':foto' => (isset($dados['foto']) && is_array($dados['foto']) && ($dados['foto']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK)
                ? $this->uploadFoto($dados['foto'])
                : null,
            ':status' => $dados['status'] ?? 'ativo',
        ];

        if ($params[':nome'] === '') {
            throw new Exception('O nome é obrigatório.');
        }

        return $this->db->execute($sql, $params);
    }

    public function atualizar($id, $dados)
    {
        $registro = $this->buscarPorId($id);
        if (!$registro) {
            throw new Exception('Registro não encontrado.');
        }

        $tag = $this->validarTag($dados);
        if ($tag !== null && $tag !== ($registro['tag'] ?? '')) {
            $sql = "SELECT id FROM `{$this->tabela}` WHERE tag = :tag AND id != :id";
            $result = $this->db->query($sql, [':tag' => $tag, ':id' => $id]);
            if ($result) {
                throw new Exception('Já existe outro registro com esta tag.');
            }
        }

        $sql = "UPDATE `{$this->tabela}` SET
                tag = :tag,
                nome = :nome,
                localizacao = :localizacao,
                area_m2 = :area_m2,
                observacoes = :observacoes,
                foto = :foto,
                status = :status
                WHERE id = :id";

        $foto = $registro['foto'];
        if (isset($dados['foto']) && is_array($dados['foto']) && ($dados['foto']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $this->removerFoto($registro['foto']);
            $foto = $this->uploadFoto($dados['foto']);
        }

        $params = [
            ':id' => $id,
            ':tag' => $tag,
            ':nome' => trim($dados['nome'] ?? ''),
            ':localizacao' => trim($dados['localizacao'] ?? '') ?: null,
            ':area_m2' => $this->validarAreaM2($dados),
            ':observacoes' => trim($dados['observacoes'] ?? '') ?: null,
            ':foto' => $foto,
            ':status' => $dados['status'] ?? 'ativo',
        ];

        if ($params[':nome'] === '') {
            throw new Exception('O nome é obrigatório.');
        }

        return $this->db->execute($sql, $params);
    }
}
