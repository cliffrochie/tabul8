<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Official Tally Sheet - {{ $event->title }} - {{ $round->name }}</title>
    <style>
        @page {
            margin: 14mm 12mm;
        }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 9.5px;
            color: #0f172a;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 8px;
            margin-bottom: 10px;
            display: table;
            width: 100%;
        }
        .header-left {
            display: table-cell;
            vertical-align: bottom;
            width: 68%;
        }
        .header-right {
            display: table-cell;
            vertical-align: bottom;
            text-align: right;
            width: 32%;
            font-size: 8.5px;
            color: #475569;
        }
        .brand {
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #475569;
            margin-bottom: 2px;
        }
        h1 {
            font-size: 16px;
            margin: 0 0 2px 0;
            color: #0f172a;
        }
        .subtitle {
            font-size: 10px;
            color: #334155;
            margin: 0;
        }
        .meta-bar {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 5px 8px;
            margin-bottom: 10px;
            font-size: 8.5px;
        }
        .meta-bar span {
            margin-right: 16px;
        }
        table.tally {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table.tally th,
        table.tally td {
            border: 1px solid #cbd5e1;
            padding: 4px 5px;
            vertical-align: middle;
        }
        table.tally th {
            background: #0f172a;
            color: #ffffff;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            text-align: center;
        }
        table.tally th.left,
        table.tally td.left {
            text-align: left;
        }
        table.tally td.center {
            text-align: center;
        }
        table.tally td.right {
            text-align: right;
        }
        table.tally tr.qualified {
            background: #f0fdf4;
        }
        .dropped {
            text-decoration: line-through;
            color: #94a3b8;
        }
        .rank-sub {
            display: block;
            font-size: 7.5px;
            color: #64748b;
        }
        .badge-q {
            display: inline-block;
            background: #047857;
            color: #ffffff;
            font-weight: bold;
            font-size: 7.5px;
            padding: 1px 4px;
            border-radius: 3px;
        }
        .badge-tie {
            display: inline-block;
            background: #d97706;
            color: #ffffff;
            font-weight: bold;
            font-size: 7px;
            padding: 1px 3px;
            border-radius: 2px;
        }
        .footnotes {
            font-size: 8px;
            color: #475569;
            margin-bottom: 14px;
        }
        .signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            page-break-inside: avoid;
        }
        .signatures td {
            padding: 14px 10px 4px 10px;
            text-align: center;
            vertical-align: bottom;
        }
        .sig-line {
            border-top: 1px solid #334155;
            padding-top: 3px;
            font-size: 8.5px;
            font-weight: bold;
        }
        .sig-role {
            font-size: 7.5px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <div class="brand">TABUL8 · Official Competition Tabulation Sheet</div>
            <h1>{{ $event->title }}</h1>
            <p class="subtitle">
                {{ $round->name }}
                @if($event->subtitle)
                    — {{ $event->subtitle }}
                @endif
            </p>
        </div>
        <div class="header-right">
            <div><strong>Generated:</strong> {{ $generatedAt }}</div>
            <div><strong>Round Status:</strong> {{ strtoupper($round->status) }}</div>
            <div><strong>Strategy:</strong> {{ strtoupper($round->scoring_strategy) }}</div>
        </div>
    </div>

    <div class="meta-bar">
        <span><strong>Top Qualifiers:</strong> {{ $round->advancement_count }}</span>
        <span><strong>Active Judges:</strong> {{ $judges->count() }}</span>
        <span><strong>Active Contestants:</strong> {{ count($results) }}</span>
        <span><strong>Weight in Overall:</strong> {{ number_format((float) $round->weight_in_overall, 2) }}%</span>
    </div>

    <table class="tally">
        <thead>
            <tr>
                <th style="width: 36px;">Rank</th>
                <th style="width: 32px;">#</th>
                <th class="left">Contestant</th>
                @foreach($judges as $judge)
                    <th>
                        J{{ $judge->judge_number }}
                        @if($judge->is_chairman)
                            (C)
                        @endif
                    </th>
                @endforeach
                <th style="width: 54px;">
                    {{ $round->scoring_strategy === 'ordinal' ? 'Rank Sum' : 'Mean' }}
                </th>
                <th style="width: 48px;">Ded.</th>
                <th style="width: 60px;">Net Comp.</th>
                <th style="width: 38px;">Qual.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($results as $row)
                @php
                    $contestant = $contestantsById->get($row->contestantId);
                    $breakdownByJudge = collect($row->judgeBreakdownSnapshot)->keyBy('judge_id');
                @endphp
                <tr class="{{ $row->isQualified ? 'qualified' : '' }}">
                    <td class="center">
                        <strong>{{ $row->finalRank }}</strong>
                        @if($row->isTied)
                            <span class="badge-tie">TIE</span>
                        @endif
                    </td>
                    <td class="center">
                        {{ $contestant?->candidate_number ?? $row->contestantId }}
                    </td>
                    <td class="left">
                        <strong>{{ $contestant?->name ?? 'Contestant #' . $row->contestantId }}</strong>
                        @if($contestant?->origin_or_group)
                            <span class="rank-sub">{{ $contestant->origin_or_group }}</span>
                        @endif
                    </td>
                    @foreach($judges as $judge)
                        @php
                            $item = $breakdownByJudge->get($judge->id);
                        @endphp
                        <td class="center">
                            @if($item)
                                <span class="{{ !empty($item['is_dropped']) ? 'dropped' : '' }}">
                                    {{ number_format((float) $item['score'], 2) }}
                                </span>
                                @if($round->scoring_strategy === 'ordinal')
                                    <span class="rank-sub">R{{ $item['rank'] }}</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                    @endforeach
                    <td class="center">
                        @if($round->scoring_strategy === 'ordinal')
                            {{ number_format($row->rankSum, 1) }}
                        @elseif($round->scoring_strategy === 'trimmed')
                            {{ number_format($row->trimmedMean, 2) }}
                        @else
                            {{ number_format($row->meanScore, 2) }}
                        @endif
                    </td>
                    <td class="center">
                        {{ $row->deductionPoints > 0 ? '-' . number_format($row->deductionPoints, 2) : '0.00' }}
                    </td>
                    <td class="right">
                        <strong>{{ number_format($row->netComposite, 2) }}</strong>
                    </td>
                    <td class="center">
                        @if($row->isQualified)
                            <span class="badge-q">Q</span>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footnotes">
        @if($round->scoring_strategy === 'trimmed')
            <span>* Strikethrough scores indicate highest and lowest judge totals dropped under Trimmed Mean.</span>
        @endif
        @php
            $tieBroken = collect($results)->filter(fn ($r) => $r->tieBreakRuleApplied !== null);
        @endphp
        @if($tieBroken->isNotEmpty())
            <div>
                * Tie-break rules applied:
                {{ $tieBroken->pluck('tieBreakRuleApplied')->unique()->implode(', ') }}
            </div>
        @endif
    </div>

    <table class="signatures">
        <tr>
            @foreach($judges as $judge)
                <td>
                    <div class="sig-line">{{ $judge->name }}</div>
                    <div class="sig-role">
                        Judge #{{ $judge->judge_number }}{{ $judge->is_chairman ? ' (Chairman)' : '' }}
                    </div>
                </td>
            @endforeach
        </tr>
    </table>

    <table class="signatures">
        <tr>
            <td style="width: 50%;">
                <div class="sig-line">Chief Tabulator</div>
                <div class="sig-role">Certified Accurate &amp; Verified</div>
            </td>
            <td style="width: 50%;">
                <div class="sig-line">Official Auditor / Event Chair</div>
                <div class="sig-role">Attested &amp; Approved</div>
            </td>
        </tr>
    </table>
</body>
</html>
