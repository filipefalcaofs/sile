import type { PageProps } from '@inertiajs/core';

export interface SharedProps extends PageProps {
    auth: {
        user: { id: number; name: string; email: string } | null;
        roles: string[];
        permissions: string[];
    };
    flash: {
        status?: string;
    };
}
