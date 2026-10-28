<?php

namespace App\Http\Requests\Gestao;

/**
 * Validação do anexo da análise: mesmos tipos e tamanho máximo do anexo da
 * vistoria (chaves vistoria.anexos.* parametrizadas, HU-014), de um arquivo
 * (`file`) ou de vários de uma vez (`files`).
 */
class AnalysisAttachmentRequest extends InspectionAttachmentRequest {}
