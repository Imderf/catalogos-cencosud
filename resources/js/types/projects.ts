/* ── Proyectos (tableros internos de Ogilvy) ─────────────────────────── */

export type ProjectRole = 'manager' | 'member';
export type Priority = 'low' | 'medium' | 'high' | 'urgent';

export interface Person {
    id: number;
    name: string;
    initials: string;
    role?: ProjectRole;
}

export interface ClientOption {
    id: number;
    name: string;
    color: string;
    logo_url?: string | null;
}

export interface LabelDto {
    id: number;
    name: string;
    color: string;
}

export interface CatalogOnCard {
    slug: string;
    name: string;
    kind_label: string;
    pages: number;
    approved: number;
    open: number;
    progress: number;
    status_label: string;
}

export interface CardDto {
    id: number;
    list_id: number;
    title: string;
    description: string | null;
    position: number;
    priority: Priority;
    priority_label: string;
    start_date: string | null;
    due_date: string | null;
    overdue: boolean;
    completed_at: string | null;
    assignees: Person[];
    labels: number[];
    catalog: CatalogOnCard | null;
    mine: boolean;
    created_by: number | null;
    comments_count: number;
    attachments_count: number;
    has_description: boolean;
    cover: string | null;
    updated_at: string | null;
    subtasks_total: number;
    subtasks_open: number;
    intake_pending: number;
}

export type SubtaskStatus = 'pending' | 'in_progress' | 'adjustments' | 'completed';

export interface StatusOption {
    value: SubtaskStatus;
    label: string;
    closed: boolean;
}

export interface SubtaskDto {
    id: number;
    card_id: number;
    title: string;
    position: number;
    status: SubtaskStatus;
    status_label: string;
    closed: boolean;
    priority: Priority;
    priority_label: string;
    due_date: string | null;
    overdue: boolean;
    assignees: Person[];
    mine: boolean;
    created_by: number | null;
    /** A page of a linked catalog: name and status follow the review. */
    automatic: boolean;
    intake_pending: number;
    deliverables_open: number;
}

export interface IntakeDto {
    id: number;
    status: 'pending' | 'approved' | 'dismissed';
    reason: string | null;
    decided_by: string | null;
    decided_at: string | null;
    adjustment: {
        id: number;
        type_label: string;
        text: string;
        author: string;
        role_label: string | null;
        open: boolean;
        version: number;
        at: string;
        url: string | null;
    } | null;
}

export interface DeliverableDto {
    id: number;
    title: string;
    status: 'pending' | 'in_progress' | 'done';
    status_label: string;
    assignee: Person | null;
    due_date: string | null;
    overdue: boolean;
    from_client: boolean;
    done_at: string | null;
}

export interface SubtaskDetail extends SubtaskDto {
    page: { number: number; label: string; catalog: string | null; url: string | null; thumb_url: string | null } | null;
    intakes: IntakeDto[];
    deliverables: DeliverableDto[];
    deliverable_statuses: { value: DeliverableDto['status']; label: string }[];
    description: string | null;
    created_by_name: string | null;
    created_at: string | null;
    comments: CommentDto[];
    activity: ActivityDto[];
}

export type FieldType = 'text' | 'textarea' | 'image' | 'number' | 'date' | 'select' | 'multiselect' | 'checkbox' | 'url' | 'user';

export interface FieldDto {
    id: number;
    name: string;
    type: FieldType;
    type_label: string;
    options: string[];
    required: boolean;
}

export type FieldValue = string | number | boolean | string[] | null;

export interface CommentDto {
    id: number;
    body: string;
    author: Person | null;
    mine: boolean;
    /** Own comment, not one that came from the client's review. */
    can_edit: boolean;
    /** The comment this one answers; replies are one level deep. */
    parent_id: number | null;
    at: string;
    edited: boolean;
    attachments: { id: number; name: string; size: number | null; image: boolean; url: string }[];
    /** Came from the client's review through an approved intake. */
    client: { role_label: string | null; page_label: string; open: boolean; url: string } | null;
}

export interface AttachmentDto {
    id: number;
    kind: 'file' | 'link';
    name: string;
    size: number | null;
    mime: string | null;
    url: string;
    image: boolean;
    author: string | null;
    mine: boolean;
    at: string;
}

export interface ActivityLine {
    id: number;
    actor: Person | null;
    text: string;
    card: { id: number; title: string } | null;
    at: string;
}

export interface ProjectOption {
    id: number;
    name: string;
    lists: { id: number; name: string }[];
}

export interface ActivityDto {
    id: number;
    actor: string;
    text: string;
    at: string;
}

export interface CardDetail extends CardDto {
    created_by_name: string | null;
    created_at: string | null;
    values: Record<string, FieldValue>;
    comments: CommentDto[];
    attachments: AttachmentDto[];
    activity: ActivityDto[];
    subtasks: SubtaskDto[];
}

export interface ListDto {
    id: number;
    name: string;
    position: number;
    kind: string | null;
    cards: CardDto[];
}

export interface ProjectSummary {
    id: number;
    name: string;
    description: string | null;
    color: string;
    client: ClientOption | null;
    archived_at: string | null;
    updated_at?: string | null;
    owner: { id: number; name: string } | null;
    my_role: ProjectRole | null;
    members: Person[];
    counts: { open: number; overdue: number; mine: number };
}

export interface ProjectBoard extends ProjectSummary {
    can: { manage: boolean; work: boolean; delete: boolean };
    labels: LabelDto[];
    fields: FieldDto[];
    lists: ListDto[];
}

export const PALETTE = ['#EF3B3A', '#0057B8', '#059669', '#D97706', '#7C3AED', '#DB2777', '#0891B2', '#6B6B76'];

export const PRIORITY_CLASS: Record<Priority, string> = {
    low: 'bg-neutral-200 text-neutral-700 dark:bg-neutral-700 dark:text-neutral-200',
    medium: 'bg-sky-100 text-sky-800 dark:bg-sky-900/60 dark:text-sky-200',
    high: 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200',
    urgent: 'bg-red-100 text-red-800 dark:bg-red-900/60 dark:text-red-200',
};
