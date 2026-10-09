<?php

namespace App\Console\Commands;

use App\Domain\Review\Inbox;
use App\Models\User;
use App\Notifications\Review\DailyDigestMail;
use Illuminate\Console\Command;

/** Each working morning: one summary email per user who chose the daily mode. */
class SendDigest extends Command
{
    protected $signature = 'review:send-digest';

    protected $description = 'Envía el resumen diario a quienes prefieren un correo por la mañana';

    public function handle(Inbox $inbox): int
    {
        $sent = 0;
        User::where('is_active', true)->where('notification_mode', 'daily')->each(function (User $user) use ($inbox, &$sent) {
            $pending = $inbox->items($user)->map(fn ($i) => [
                'title' => $i['title'], 'detail' => $i['detail'], 'url' => route('pages.show', [$i['catalog'], $i['page']]),
            ])->values()->all();
            $recent = $user->notifications()->where('created_at', '>=', now()->subDay())->latest()->get()->map(fn ($n) => [
                'text' => $n->data['text'] ?? '', 'actor' => $n->data['actor'] ?? '',
                'url' => $n->data['url'] ?? (! empty($n->data['page_number']) ? route('pages.show', [$n->data['catalog_slug'], $n->data['page_number']]) : route('notifications.index')),
            ])->values()->all();

            if ($pending === [] && $recent === []) {
                return;
            }
            $user->notify(new DailyDigestMail($pending, $recent));
            $sent++;
        });

        $this->info("Resúmenes enviados: {$sent}");

        return self::SUCCESS;
    }
}
