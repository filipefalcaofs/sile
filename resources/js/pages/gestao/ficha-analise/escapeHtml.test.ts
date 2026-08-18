import { describe, expect, it } from 'vitest';
import { escapeHtml } from './show';

describe('escapeHtml', () => {
    it('escapa todos os caracteres especiais em uma string misturada', () => {
        expect(escapeHtml(`<script>alert('x')</script> & "`)).toBe(
            '&lt;script&gt;alert(&#39;x&#39;)&lt;/script&gt; &amp; &quot;',
        );
    });

    it('mantém inalterada uma string sem caracteres especiais', () => {
        expect(escapeHtml('Texto seguro 123')).toBe('Texto seguro 123');
    });
});
