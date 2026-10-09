import { LucideIcon } from 'lucide-react';

export type RoleValue = 'admin' | 'agencia' | 'lectura' | 'marketing' | 'planificacion' | 'comercial';

export interface Sector {
    id: number;
    slug: string;
    name: string;
    abbr: string;
    /** Family: FOOD (Líquidos, DPH, Rancho, PLS, PFT) or NON FOOD (Bazar, Electro, Textil). */
    group: 'food' | 'non_food';
}

export interface RoleOption {
    value: RoleValue;
    label: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
    organization: string | null;
    role: RoleValue | null;
    role_label: string | null;
    side?: 'Ogilvy' | 'Cencosud';
    sectors: Sector[];
    is_active: boolean;
    must_change_password: boolean;
    notification_mode?: 'instant' | 'daily';
    /** Chime in the browser when a notification arrives. */
    notification_sound?: boolean;
    created_at?: string;
    avatar?: string;
}

export interface AuthUser extends User {
    acts_as_agency: boolean;
    /** Ogilvy · Solo lectura: sees everything Ogilvy sees, acts on nothing. */
    read_only: boolean;
    can: {
        manage_users: boolean;
        manage_catalogs: boolean;
        manage_sla: boolean;
        projects: boolean;
        manage_clients: boolean;
    };
}

export interface Auth {
    user: AuthUser;
}

export interface Flash {
    success: string | null;
    error: string | null;
    key?: string | null;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /** URL prefixes that mark the item active (defaults to the url itself) */
    match?: string[];
}

export interface SharedData {
    name: string;
    auth: Auth;
    flash: Flash;
    workday: Workday | null;
    inbox: InboxCounts | null;
    unread: number;
    round: { approved: number; pages: number } | null;
    [key: string]: unknown;
}

/* ── Revisión de catálogos ─────────────────────────────────────────────── */

export type ClockState = 'corriendo' | 'urgente' | 'vencido' | 'espera';

export interface ClockDto {
    type: 'ajuste' | 'aprobacion';
    state: ClockState;
    where: string;
    total: number;
    consumed: number;
    remaining: number;
    pct: number;
    open: number;
    overdue: number;
    frozen: boolean;
    detail: string;
}

export interface Totals {
    total: number;
    open: number;
    approved: number;
    defects: number;
    overdue: number;
    approvalsOverdue: number;
}

export interface CatalogSummary {
    id: number;
    slug: string;
    name: string;
    kind: 'octavilla' | 'cuartilla' | 'volante';
    kind_label: string;
    unsubmitted: number;
    brand: string;
    client: string;
    pages_count: number;
    published_at: string;
    archived_at: string | null;
    totals: Totals;
    status_label: string;
    progress: number;
    /** Board card of this catalog; only present for Ogilvy people. */
    project?: { id: number; name: string; color: string; card_id: number } | null;
}

export interface PageSector {
    slug: string;
    name: string;
    abbr: string;
}

export interface LocationDto {
    role: string | null;
    label: string;
    working: boolean;
    approved: boolean;
}

export interface PageCard {
    id: number;
    number: number;
    label: string;
    sectors: PageSector[];
    sectors_label: string;
    version: number;
    open: number;
    total: number;
    approved: boolean;
    submitted: boolean;
    has_access: boolean;
    has_defect: boolean;
    location: LocationDto;
    is_mine: boolean;
    clock: ClockDto | null;
    thumb_url: string | null;
}

export interface Ability {
    allowed: boolean;
    reason: string | null;
}

export type BoxDto = {
    x: number;
    y: number;
    w: number;
    h: number;
};

export type AdjustmentTypeValue = 'precio' | 'descripcion' | 'imagen' | 'diseno' | 'dinamica' | 'ingresa_dinamica' | 'compaginada' | 'pata' | 'otro';

export interface TypeOption {
    value: AdjustmentTypeValue;
    label: string;
}

export interface AdjustmentAttachmentDto {
    id: number;
    kind: 'file' | 'link';
    name: string;
    size: number | null;
    image: boolean;
    url: string;
}

export interface AdjustmentDto {
    id: number;
    index: number;
    /** Waiting for Ogilvy behind higher-priority tasks: the clock has not started. */
    queued?: boolean;
    task_priority?: string | null;
    /** Ogilvy only: where the comment sits on the board. */
    board?: { status: 'pending' | 'approved' | 'dismissed'; url: string } | null;
    type: AdjustmentTypeValue;
    /** Every kind chosen; `type` is the first. */
    types: AdjustmentTypeValue[];
    type_label: string;
    text: string;
    box: BoxDto;
    status: 'open' | 'resolved';
    is_defect: boolean;
    author: string;
    origin_role_label: string;
    date: string;
    version: number;
    resolved_in: number | null;
    stage: string | null;
    stage_label: string | null;
    where: string;
    sla: number;
    remaining: number | null;
    consumed_pct: number;
    state: ClockState;
    trace: string[];
    validations: { by: string; role_label: string; note: string | null; date: string }[];
    replies: { by: string; text: string; date: string }[];
    /** PDFs, images or links the client attached as reference. */
    attachments: AdjustmentAttachmentDto[];
    can: { validate: boolean; resolve: boolean; reply: boolean; update: boolean; delete: boolean };
}

export interface PageVersionDto {
    number: number;
    date: string;
    url: string;
}

export interface ChainStep {
    role: string;
    label: string;
    done_by: string | null;
    active: boolean;
    suspended: boolean;
}

export interface PageDetail extends PageCard {
    viewing_version: number;
    is_historical: boolean;
    image_url: string | null;
    versions: PageVersionDto[];
    adjustments: AdjustmentDto[];
    approvals: {
        role: string;
        role_label: string;
        role_full: string;
        phase: 'validation' | 'print';
        by: string;
        date: string;
        version: number;
        delay: number;
    }[];
    defects: { by: string; role_label: string; date: string; version: number; reason: string; type_label: string }[];
    chain: ChainStep[];
    print_chain: ChainStep[];
    agency_step: { working: boolean; label: string };
    phase: 'preparation' | 'validation' | 'print' | 'closed';
    stage: string | null;
    next_role_label: string | null;
    next_role_short: string | null;
    already_approved: boolean;
    already_printed: boolean;
    suspended: boolean;
    can: { comment: Ability; approve: Ability; approve_print: Ability; reopen: Ability; upload_version: Ability; submit: Ability };
}

export interface Workday {
    open: boolean;
    hours: string;
    label: string;
}

export interface InboxCounts {
    approvals: number;
    adjustments: number;
    total: number;
}
