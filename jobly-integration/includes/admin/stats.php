<?php
/**
 * Statistika on Pregled: period switcher, KPI cards, SVG chart, job table, sources, stages.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Period from the URL: 7, 30 or 90 days, or a custom range.
 *
 * @return array{period: string, from: string, to: string}
 */
function jobly_integration_stats_range() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display filters.
	$period = isset( $_GET['period'] ) ? sanitize_key( wp_unslash( $_GET['period'] ) ) : '30';
	$today  = gmdate( 'Y-m-d' ); // Jobly counts days in UTC and rejects future dates.
	if ( 'custom' === $period ) {
		$from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
		$to   = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
		$ok   = static function ( $d ) {
			return 1 === preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
		};
		if ( $ok( $from ) && $ok( $to ) ) {
			$to   = min( $to, $today );
			$from = min( $from, $to );
			$from = max( $from, gmdate( 'Y-m-d', strtotime( $to . ' -365 days' ) ) );
			return array(
				'period' => 'custom',
				'from'   => $from,
				'to'     => $to,
			);
		}
		$period = '30';
	}
	// phpcs:enable
	$days = in_array( $period, array( '7', '30', '90' ), true ) ? (int) $period : 30;
	return array(
		'period' => (string) $days,
		'from'   => gmdate( 'Y-m-d', strtotime( $today . ' -' . ( $days - 1 ) . ' days' ) ),
		'to'     => $today,
	);
}

/**
 * A metric value: number, or "—" with an explanation when Jobly does not track it.
 *
 * @param int|float|null $value Metric.
 * @return string Escaped HTML.
 */
function jobly_integration_metric( $value ) {
	if ( null === $value ) {
		return '<span class="jobly-na" title="' . esc_attr__( 'Jobly te vrednosti ne beleži.', 'jobly-integration' ) . '" aria-label="' . esc_attr__( 'Ni podatka: Jobly te vrednosti ne beleži.', 'jobly-integration' ) . '">—</span>';
	}
	return esc_html( number_format_i18n( (float) $value ) );
}

/**
 * Conversion in percent, computed from views and applications (null when either is missing).
 *
 * @param int|float|null $views Job views.
 * @param int|float|null $apps  Applications.
 * @return float|null
 */
function jobly_integration_conversion( $views, $apps ) {
	if ( null === $views || null === $apps || (float) $views <= 0 ) {
		return null;
	}
	return round( 100 * (float) $apps / (float) $views, 1 );
}

/**
 * Inline SVG chart of daily views and applications (two scales), with a hidden data table.
 *
 * @param array[] $daily Rows: date, jobViews, applications.
 */
function jobly_integration_stats_chart( array $daily ) {
	$n = count( $daily );
	if ( $n < 2 ) {
		return;
	}
	$w     = 760;
	$h     = 240;
	$left  = 44;
	$right = 44;
	$top   = 14;
	$bot   = 30;
	$pw    = $w - $left - $right;
	$ph    = $h - $top - $bot;
	$max_v = 4;
	$max_a = 4;
	foreach ( $daily as $row ) {
		$max_v = max( $max_v, (int) ( $row['jobViews'] ?? 0 ) );
		$max_a = max( $max_a, (int) ( $row['applications'] ?? 0 ) );
	}
	$series = static function ( $key, $max ) use ( $daily, $n, $left, $top, $pw, $ph ) {
		$points = array();
		foreach ( $daily as $i => $row ) {
			if ( ! isset( $row[ $key ] ) ) {
				continue;
			}
			$points[] = round( $left + $pw * $i / ( $n - 1 ), 1 ) . ',' . round( $top + $ph - $ph * (float) $row[ $key ] / $max, 1 );
		}
		return $points;
	};
	$views  = $series( 'jobViews', $max_v );
	$apps   = $series( 'applications', $max_a );

	$svg  = '<svg class="jobly-chart" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-labelledby="jobly-chart-t jobly-chart-d" preserveAspectRatio="xMidYMid meet">';
	$svg .= '<title id="jobly-chart-t">' . esc_html__( 'Ogledi oglasov in prijave po dnevih', 'jobly-integration' ) . '</title>';
	$svg .= '<desc id="jobly-chart-d">' . esc_html(
		sprintf(
			/* translators: 1: first date, 2: last date, 3: max daily views, 4: max daily applications. */
			__( 'Dnevni ogledi (do %3$d) in prijave (do %4$d) od %1$s do %2$s. Podatki so v tabeli pod grafom.', 'jobly-integration' ),
			(string) $daily[0]['date'],
			(string) $daily[ $n - 1 ]['date'],
			$max_v,
			$max_a
		)
	) . '</desc>';
	for ( $g = 0; $g <= 4; $g++ ) {
		$y    = round( $top + $ph * $g / 4, 1 );
		$svg .= '<line x1="' . $left . '" x2="' . ( $w - $right ) . '" y1="' . $y . '" y2="' . $y . '" stroke="#e2e8f0" stroke-width="1"/>';
		$svg .= '<text x="' . ( $left - 8 ) . '" y="' . ( $y + 4 ) . '" text-anchor="end" font-size="11" fill="#2563eb">' . esc_html( (string) round( $max_v * ( 4 - $g ) / 4 ) ) . '</text>';
		$svg .= '<text x="' . ( $w - $right + 8 ) . '" y="' . ( $y + 4 ) . '" font-size="11" fill="#059669">' . esc_html( (string) round( $max_a * ( 4 - $g ) / 4 ) ) . '</text>';
	}
	foreach ( array( 0, (int) floor( ( $n - 1 ) / 2 ), $n - 1 ) as $k => $i ) {
		$anchor = 0 === $k ? 'start' : ( 2 === $k ? 'end' : 'middle' );
		$svg   .= '<text x="' . round( $left + $pw * $i / ( $n - 1 ), 1 ) . '" y="' . ( $h - 8 ) . '" text-anchor="' . $anchor . '" font-size="11" fill="#64748b">' . esc_html( wp_date( 'j. n.', strtotime( $daily[ $i ]['date'] . ' 12:00:00 UTC' ) ) ) . '</text>';
	}
	if ( count( $views ) > 1 ) {
		$area = $left . ',' . ( $top + $ph ) . ' ' . implode( ' ', $views ) . ' ' . ( $left + $pw ) . ',' . ( $top + $ph );
		$svg .= '<polyline points="' . esc_attr( $area ) . '" fill="#2563eb" opacity="0.08" stroke="none"/>';
		$svg .= '<polyline points="' . esc_attr( implode( ' ', $views ) ) . '" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>';
	}
	if ( count( $apps ) > 1 ) {
		$svg .= '<polyline points="' . esc_attr( implode( ' ', $apps ) ) . '" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>';
	}
	$svg .= '</svg>';

	echo '<div class="jobly-chartwrap">';
	echo wp_kses( $svg, jobly_integration_svg_kses() );
	echo '<p class="jobly-legend"><span class="jobly-legend__dot jobly-legend__dot--views"></span>' . esc_html__( 'Ogledi (prijavljeni iskalci, leva os)', 'jobly-integration' ) . '<span class="jobly-legend__dot jobly-legend__dot--apps"></span>' . esc_html__( 'Prijave (desna os)', 'jobly-integration' ) . '</p>';
	echo '<table class="jobly-sr"><caption>' . esc_html__( 'Podatki grafa po dnevih', 'jobly-integration' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Datum', 'jobly-integration' ) . '</th><th scope="col">' . esc_html__( 'Ogledi', 'jobly-integration' ) . '</th><th scope="col">' . esc_html__( 'Prijave', 'jobly-integration' ) . '</th></tr></thead><tbody>';
	foreach ( $daily as $row ) {
		echo '<tr><th scope="row">' . esc_html( (string) $row['date'] ) . '</th><td>' . wp_kses_post( jobly_integration_metric( $row['jobViews'] ?? null ) ) . '</td><td>' . wp_kses_post( jobly_integration_metric( $row['applications'] ?? null ) ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
}

/**
 * Application source -> label (unknown values are shown as given).
 *
 * @param string $source API source.
 * @return string
 */
function jobly_integration_source_label( $source ) {
	$labels = array(
		'jobly'   => 'Jobly',
		'embed'   => __( 'Vgrajen obrazec', 'jobly-integration' ),
		'guest'   => __( 'Prijava brez računa', 'jobly-integration' ),
		'other'   => __( 'Drugo', 'jobly-integration' ),
		'unknown' => __( 'Neznano', 'jobly-integration' ),
	);
	return $labels[ $source ] ?? $source;
}

/**
 * Horizontal bars (sources, stages).
 *
 * @param string $title Panel title.
 * @param array  $rows  label => count.
 */
function jobly_integration_stats_bars( $title, array $rows ) {
	echo '<section class="jobly-panel"><header class="jobly-panel__head"><h2>' . esc_html( $title ) . '</h2></header>';
	if ( ! $rows ) {
		echo '<p class="jobly-muted">' . esc_html__( 'Ni podatkov za izbrano obdobje.', 'jobly-integration' ) . '</p></section>';
		return;
	}
	$max = max( 1, (int) max( $rows ) );
	echo '<ul class="jobly-bars">';
	foreach ( $rows as $label => $count ) {
		echo '<li><span class="jobly-bars__label">' . esc_html( (string) $label ) . '</span><span class="jobly-bars__track"><span style="width:' . esc_attr( (string) round( 100 * $count / $max ) ) . '%"></span></span><span class="jobly-bars__n">' . esc_html( number_format_i18n( $count ) ) . '</span></li>';
	}
	echo '</ul></section>';
}

/**
 * Render the statistics section. Returns false when the API has no statistics (older Jobly).
 *
 * @return bool Rendered.
 */
function jobly_integration_render_stats() {
	$range = jobly_integration_stats_range();
	$res   = jobly_integration_stats( $range['from'], $range['to'] );
	if ( 200 !== $res['code'] ) {
		return false;
	}
	$data   = $res['data'];
	$totals = (array) ( $data['totals'] ?? array() );
	$views  = $totals['jobViews'] ?? null;
	$apps   = $totals['applications'] ?? null;
	$conv   = jobly_integration_conversion( $views, $apps );

	$url     = static function ( array $args ) {
		return jobly_integration_admin_url( 'jobly-integration', $args );
	};
	$periods = array(
		'7'  => __( '7 dni', 'jobly-integration' ),
		'30' => __( '30 dni', 'jobly-integration' ),
		'90' => __( '90 dni', 'jobly-integration' ),
	);

	echo '<section class="jobly-panel jobly-stats-panel"><header class="jobly-panel__head"><h2>' . esc_html__( 'Statistika', 'jobly-integration' ) . '</h2><nav class="jobly-seg" aria-label="' . esc_attr__( 'Obdobje', 'jobly-integration' ) . '">';
	foreach ( $periods as $key => $label ) {
		echo '<a href="' . esc_url( $url( array( 'period' => $key ) ) ) . '"' . ( (string) $key === $range['period'] ? ' class="is-active" aria-current="true"' : '' ) . '>' . esc_html( $label ) . '</a>';
	}
	echo '<a href="#jobly-custom" class="' . ( 'custom' === $range['period'] ? 'is-active' : '' ) . '">' . esc_html__( 'Po meri', 'jobly-integration' ) . '</a></nav></header>';
	echo '<form id="jobly-custom" class="jobly-range" method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="jobly-integration"><input type="hidden" name="period" value="custom">';
	echo '<label>' . esc_html__( 'Od', 'jobly-integration' ) . ' <input type="date" name="from" value="' . esc_attr( $range['from'] ) . '" max="' . esc_attr( gmdate( 'Y-m-d' ) ) . '"></label> <label>' . esc_html__( 'Do', 'jobly-integration' ) . ' <input type="date" name="to" value="' . esc_attr( $range['to'] ) . '" max="' . esc_attr( gmdate( 'Y-m-d' ) ) . '"></label> <button type="submit" class="button">' . esc_html__( 'Prikaži', 'jobly-integration' ) . '</button></form>';

	$views_hint = __( 'Ogledi neprijavljenih obiskovalcev se še ne štejejo.', 'jobly-integration' );
	$kpis       = array(
		array( 'eye', $views, __( 'Ogledi (prijavljeni iskalci)', 'jobly-integration' ), 'blue', false ),
		array( 'inbox', $apps, __( 'Prijave', 'jobly-integration' ), 'green', false ),
		array( 'users', $totals['hires'] ?? null, __( 'Zaposlitve', 'jobly-integration' ), 'violet', false ),
		array( 'percent', $conv, __( 'Konverzija', 'jobly-integration' ), 'amber', true ),
		array( 'briefcase', $totals['openJobs'] ?? null, __( 'Odprta mesta', 'jobly-integration' ), 'blue', false ),
	);
	echo '<div class="jobly-kpis">';
	foreach ( $kpis as $kpi ) {
		echo '<div class="jobly-kpi"' . ( 'eye' === $kpi[0] ? ' title="' . esc_attr( $views_hint ) . '"' : '' ) . '><span class="jobly-stat__icon jobly-tone--' . esc_attr( $kpi[3] ) . '">';
		jobly_integration_icon( $kpi[0], '', 20 );
		echo '</span><div><strong>' . wp_kses_post( jobly_integration_metric( $kpi[1] ) ) . ( $kpi[4] && null !== $kpi[1] ? ' %' : '' ) . '</strong><span>' . esc_html( $kpi[2] ) . '</span></div></div>';
	}
	echo '</div>';

	$daily = array_values( (array) ( $data['daily'] ?? array() ) );
	if ( count( $daily ) > 1 ) {
		jobly_integration_stats_chart( $daily );
	} else {
		echo '<p class="jobly-muted">' . esc_html__( 'Za izbrano obdobje ni dnevnih podatkov.', 'jobly-integration' ) . '</p>';
	}
	echo '</section>';

	// Job performance, sortable by applications or views.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display sort.
	$sort = isset( $_GET['sort'] ) && 'views' === sanitize_key( wp_unslash( $_GET['sort'] ) ) ? 'jobViews' : 'applications';
	$jobs = array_values( (array) ( $data['jobs'] ?? array() ) );
	usort(
		$jobs,
		static function ( $a, $b ) use ( $sort ) {
			return (int) ( $b[ $sort ] ?? 0 ) <=> (int) ( $a[ $sort ] ?? 0 );
		}
	);
	$keep = array(
		'period' => $range['period'],
		'from'   => $range['from'],
		'to'     => $range['to'],
	);
	echo '<div class="jobly-grid"><section class="jobly-panel jobly-panel--wide"><header class="jobly-panel__head"><h2>' . esc_html__( 'Uspešnost oglasov', 'jobly-integration' ) . '</h2></header>';
	if ( ! $jobs ) {
		echo '<p class="jobly-muted">' . esc_html__( 'Ni podatkov za izbrano obdobje.', 'jobly-integration' ) . '</p>';
	} else {
		echo '<table class="widefat striped jobly-table"><thead><tr><th scope="col">' . esc_html__( 'Oglas', 'jobly-integration' ) . '</th>';
		echo '<th scope="col" aria-sort="' . ( 'jobViews' === $sort ? 'descending' : 'none' ) . '"><a href="' . esc_url( $url( array_merge( $keep, array( 'sort' => 'views' ) ) ) ) . '" title="' . esc_attr( $views_hint ) . '">' . esc_html__( 'Ogledi', 'jobly-integration' ) . '</a></th>';
		echo '<th scope="col" aria-sort="' . ( 'applications' === $sort ? 'descending' : 'none' ) . '"><a href="' . esc_url( $url( array_merge( $keep, array( 'sort' => 'applications' ) ) ) ) . '">' . esc_html__( 'Prijave', 'jobly-integration' ) . '</a></th>';
		echo '<th scope="col">' . esc_html__( 'Konverzija', 'jobly-integration' ) . '</th></tr></thead><tbody>';
		foreach ( $jobs as $job ) {
			$c    = jobly_integration_conversion( $job['jobViews'] ?? null, $job['applications'] ?? null );
			$link = jobly_integration_admin_url(
				'jobly-jobs',
				array(
					'action' => 'show',
					'job'    => (string) ( $job['slug'] ?? '' ),
				)
			);
			echo '<tr><td><a href="' . esc_url( $link ) . '">' . esc_html( (string) ( $job['title'] ?? '' ) ) . '</a></td><td>' . wp_kses_post( jobly_integration_metric( $job['jobViews'] ?? null ) ) . '</td><td>' . wp_kses_post( jobly_integration_metric( $job['applications'] ?? null ) ) . '</td><td>' . wp_kses_post( jobly_integration_metric( $c ) ) . ( null === $c ? '' : ' %' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '</section>';

	$sources = array();
	foreach ( (array) ( $data['sources'] ?? array() ) as $row ) {
		$sources[ jobly_integration_source_label( (string) ( $row['source'] ?? '' ) ) ] = (int) ( $row['applications'] ?? 0 );
	}
	$stages = array();
	foreach ( (array) ( $data['stages'] ?? array() ) as $row ) {
		$stages[ jobly_integration_stage_label( (string) ( $row['stage'] ?? '' ) ) ] = (int) ( $row['count'] ?? 0 );
	}
	jobly_integration_stats_bars( __( 'Viri prijav', 'jobly-integration' ), $sources );
	jobly_integration_stats_bars( __( 'Faze kandidatov', 'jobly-integration' ), $stages );
	echo '</div>';
	return true;
}
