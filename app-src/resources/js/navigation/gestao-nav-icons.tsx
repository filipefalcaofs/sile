import type { ReactNode } from 'react';
import {
    AlertIcon,
    FileIcon,
    GearIcon,
    GridIcon,
    GroupIcon,
    ListIcon,
    LockIcon,
    MailIcon,
    MapPinIcon,
    PlugInIcon,
    ShieldIcon,
    TableIcon,
    TagIcon,
    UserCircleIcon,
} from '@/components/icons';
import type { GestaoNavIcon } from '@/navigation/gestao-nav';

const ICONS: Record<GestaoNavIcon, ReactNode> = {
    grid: <GridIcon />,
    list: <ListIcon />,
    file: <FileIcon />,
    user: <UserCircleIcon />,
    map: <MapPinIcon />,
    table: <TableIcon />,
    tag: <TagIcon />,
    gear: <GearIcon />,
    plugin: <PlugInIcon />,
    alert: <AlertIcon />,
    shield: <ShieldIcon />,
    group: <GroupIcon />,
    lock: <LockIcon />,
    mail: <MailIcon />,
};

export function gestaoNavIcon(icon: GestaoNavIcon): ReactNode {
    return ICONS[icon];
}
