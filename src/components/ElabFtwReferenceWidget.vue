<template>
	<component :is="accessible ? 'a' : 'div'"
		class="elabftw-reference"
		:class="{ 'elabftw-reference--inaccessible': !accessible }"
		:href="accessible ? richObject.url : undefined"
		:target="accessible ? '_blank' : undefined"
		:rel="accessible ? 'noopener noreferrer' : undefined">
		<div class="elabftw-reference__header">
			<!-- The icon tells the type (experiment or resource); alt and title keep it accessible -->
			<img class="elabftw-reference__icon"
				:src="iconUrl"
				:alt="typeLabel"
				:title="typeLabel">
			<span class="elabftw-reference__title" :title="title">{{ title }}</span>
			<!-- State of the entry, right after the title -->
			<span v-if="accessible && stateIcons.length > 0" class="elabftw-reference__states">
				<img v-for="state in stateIcons"
					:key="state.key"
					class="elabftw-reference__state"
					:src="state.url"
					:alt="state.label"
					:title="state.label">
			</span>
			<span class="elabftw-reference__id">#{{ richObject.id }}</span>
		</div>

		<template v-if="accessible">
			<p v-if="richObject.excerpt" class="elabftw-reference__excerpt">
				{{ richObject.excerpt }}
			</p>

			<!-- Plain div/span on purpose: host apps (Text, Talk...) often restyle dl/dt/dd globally -->
			<div class="elabftw-reference__meta">
				<div class="elabftw-reference__field">
					<span class="elabftw-reference__label">{{ t('elabftw_picker', 'Category') }}</span>
					<span class="elabftw-reference__value">
						<span v-if="richObject.category"
							class="elabftw-reference__chip elabftw-reference__chip--upper"
							:style="{ '--chip-color': categoryColor }"
							:title="richObject.category">
							{{ richObject.category }}
						</span>
						<template v-else>
							—
						</template>
					</span>
				</div>
				<div class="elabftw-reference__field">
					<span class="elabftw-reference__label">{{ t('elabftw_picker', 'Author') }}</span>
					<span class="elabftw-reference__value" :title="richObject.author || undefined">
						{{ richObject.author || '—' }}
					</span>
				</div>
				<div class="elabftw-reference__field">
					<span class="elabftw-reference__label">{{ t('elabftw_picker', 'Last modified') }}</span>
					<span class="elabftw-reference__value">
						<NcDateTime v-if="modifiedTimestamp"
							:timestamp="modifiedTimestamp"
							:ignore-seconds="true" />
						<template v-else>
							—
						</template>
					</span>
				</div>
				<div class="elabftw-reference__field">
					<span class="elabftw-reference__label">{{ t('elabftw_picker', 'Status') }}</span>
					<span class="elabftw-reference__value">
						<span v-if="richObject.status"
							class="elabftw-reference__chip elabftw-reference__chip--dot"
							:style="{ '--chip-color': statusColor }"
							:title="richObject.status">
							{{ richObject.status }}
						</span>
						<template v-else>
							—
						</template>
					</span>
				</div>
			</div>

			<div v-if="tags.length > 0" class="elabftw-reference__chips">
				<img class="elabftw-reference__state"
					:src="imageUrl('tags.svg')"
					:alt="t('elabftw_picker', 'Tags')"
					:title="t('elabftw_picker', 'Tags')">
				<span v-for="tag in visibleTags"
					:key="'tag-' + tag"
					class="elabftw-reference__tag">
					{{ tag }}
				</span>
				<span v-if="hiddenTagsCount > 0"
					class="elabftw-reference__tag elabftw-reference__tag--more"
					:title="hiddenTags">
					+{{ hiddenTagsCount }}
				</span>
			</div>
		</template>
		<p v-else class="elabftw-reference__no-access">
			{{ t('elabftw_picker', 'You do not have access to this entry') }}
		</p>
	</component>
</template>

<script>
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import { translate as t } from '@nextcloud/l10n'
import { imagePath } from '@nextcloud/router'

const MAX_VISIBLE_TAGS = 5

export default {
	name: 'ElabFtwReferenceWidget',

	components: {
		NcDateTime,
	},

	props: {
		richObjectType: {
			type: String,
			default: '',
		},
		richObject: {
			type: Object,
			default: () => ({}),
		},
		accessible: {
			type: Boolean,
			default: true,
		},
	},

	computed: {
		iconUrl() {
			// One icon per type (img/experiment.svg and img/resource.svg)
			return this.imageUrl(this.richObject.type === 'items' ? 'resource.svg' : 'experiment.svg')
		},
		title() {
			return this.richObject.title || `#${this.richObject.id}`
		},
		typeLabel() {
			return this.richObject.type === 'items'
				? t('elabftw_picker', 'Resource')
				: t('elabftw_picker', 'Experiment')
		},
		modifiedTimestamp() {
			// PHP gives seconds, NcDateTime expects milliseconds
			return this.richObject.modified_ts ? this.richObject.modified_ts * 1000 : null
		},
		statusColor() {
			return this.toCssColor(this.richObject.status_color)
		},
		categoryColor() {
			return this.toCssColor(this.richObject.category_color)
		},
		stateIcons() {
			// Same Font Awesome icons as eLabFTW, copied in img/
			const states = [
				{ key: 'signed', active: this.richObject.signed, file: 'signature.svg', label: t('elabftw_picker', 'Signed') },
				{ key: 'locked', active: this.richObject.locked, file: 'lock.svg', label: t('elabftw_picker', 'Locked') },
				{ key: 'timestamped', active: this.richObject.timestamped, file: 'calendar-check.svg', label: t('elabftw_picker', 'Timestamped') },
				{ key: 'archived', active: this.richObject.archived, file: 'box-archive.svg', label: t('elabftw_picker', 'Archived') },
			]
			return states
				.filter((state) => state.active)
				.map((state) => ({ ...state, url: this.imageUrl(state.file) }))
		},
		tags() {
			return Array.isArray(this.richObject.tags) ? this.richObject.tags : []
		},
		visibleTags() {
			return this.tags.slice(0, MAX_VISIBLE_TAGS)
		},
		hiddenTags() {
			return this.tags.slice(MAX_VISIBLE_TAGS).join(', ')
		},
		hiddenTagsCount() {
			return Math.max(0, this.tags.length - MAX_VISIBLE_TAGS)
		},
	},

	methods: {
		t,

		imageUrl(name) {
			return imagePath('elabftw_picker', name)
		},

		toCssColor(color) {
			if (!color) {
				return 'var(--color-text-maxcontrast)'
			}
			return color.startsWith('#') ? color : `#${color}`
		},
	},
}
</script>

<style lang="scss" scoped>
.elabftw-reference {
	display: flex;
	flex-direction: column;
	gap: 8px;
	width: 100%;
	box-sizing: border-box;
	padding: 12px 16px;
	text-align: start;
	text-decoration: none;
	color: var(--color-main-text);
	background-color: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);

	&:hover,
	&:focus-visible {
		background-color: var(--color-background-hover);
	}

	&--inaccessible {
		cursor: default;
		opacity: 0.8;

		&:hover {
			background-color: var(--color-main-background);
		}
	}

	&__header {
		display: flex;
		align-items: center;
		gap: 8px;
		min-width: 0;
	}

	&__icon {
		width: 20px;
		height: 20px;
		flex-shrink: 0;
		// SVG icons are black: follow the theme
		filter: var(--background-invert-if-dark);
	}

	&__title {
		overflow: hidden;
		font-weight: bold;
		font-size: 1.1em;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__states {
		display: flex;
		flex-shrink: 0;
		align-items: center;
		gap: 6px;
	}

	// Small state icons (signed, locked...) and the tags icon
	&__state {
		width: 14px;
		height: 14px;
		flex-shrink: 0;
		opacity: 0.8;
		filter: var(--background-invert-if-dark);
	}

	&__id {
		flex-shrink: 0;
		color: var(--color-text-maxcontrast);
	}

	&__excerpt {
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 2;
		line-clamp: 2;
		margin: 0;
		overflow: hidden;
		color: var(--color-text-maxcontrast);
		font-size: 0.9em;
		line-height: 1.4;
	}

	&__meta {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
		gap: 8px 16px;
		align-items: start;
		margin: 0;
		padding: 0;
		text-align: start;
	}

	&__field {
		display: flex;
		flex-direction: column;
		align-items: flex-start;
		gap: 2px;
		min-width: 0;
		margin: 0;
		padding: 0;
		overflow: hidden;
		text-align: start;
	}

	&__label {
		display: block;
		max-width: 100%;
		margin: 0;
		padding: 0;
		color: var(--color-text-maxcontrast);
		font-size: 0.85em;
		line-height: 1.3;
		text-align: start;
	}

	&__value {
		display: block;
		max-width: 100%;
		min-width: 0;
		margin: 0;
		padding: 0;
		overflow: hidden;
		text-align: start;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__chips {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 6px;
	}

	// Colored pill (status, category): the text keeps the theme color so light colors stay readable
	// inline-block (not inline-flex) so that a long text is cut with an ellipsis inside the pill
	&__chip {
		display: inline-block;
		box-sizing: border-box;
		max-width: 100%;
		padding: 0 10px;
		overflow: hidden;
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius-pill, 999px);
		text-overflow: ellipsis;
		white-space: nowrap;
		vertical-align: top;
		// Fallback first, then tinted background and border where color-mix is supported
		background: var(--color-background-dark);
		background: color-mix(in srgb, var(--chip-color) 18%, transparent);
		border-color: color-mix(in srgb, var(--chip-color) 45%, transparent);

		// Category: capitals like in eLabFTW (display only, the title is not modified)
		&--upper {
			font-size: 0.85em;
			font-weight: 600;
			letter-spacing: 0.03em;
			text-transform: uppercase;
		}

		// Only the status has a dot (like in eLabFTW), the category has none
		&--dot::before {
			content: '';
			display: inline-block;
			width: 8px;
			height: 8px;
			margin-inline-end: 6px;
			border-radius: 50%;
			background-color: var(--chip-color);
			vertical-align: middle;
		}
	}

	// Filled and in the main text color, so tags stay readable on any background
	&__tag {
		padding: 1px 10px;
		border: 1px solid var(--color-border-dark);
		border-radius: var(--border-radius-pill, 999px);
		background: var(--color-background-dark);
		color: var(--color-main-text);
		font-size: 0.9em;
		line-height: 1.4;

		&--more {
			border-style: dashed;
		}
	}

	&__no-access {
		margin: 0;
		color: var(--color-text-maxcontrast);
	}
}
</style>
