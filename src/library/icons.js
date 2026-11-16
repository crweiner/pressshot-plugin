/**
 * Sidebar icons drawn in the `@wordpress/icons` style: 24px, outline, 1.5 stroke.
 */
import { G, Path, SVG } from '@wordpress/primitives';

/** A plain outline folder, for every folder row. */
export const folder = (
	<SVG
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		style={ { fill: 'none' } }
		stroke="currentColor"
		strokeWidth="1.5"
		strokeLinejoin="round"
	>
		<Path d="M4.75 7C4.75 6.30964 5.30964 5.75 6 5.75H9.58579C9.851 5.75 10.1054 5.85536 10.2929 6.04289L11.7071 7.45711C11.8946 7.64464 12.149 7.75 12.4142 7.75H18C18.6904 7.75 19.25 8.30964 19.25 9V17C19.25 17.6904 18.6904 18.25 18 18.25H6C5.30964 18.25 4.75 17.6904 4.75 17V7Z" />
	</SVG>
);

/**
 * All shots: core's `image` glyph in front and its `video` glyph stacked behind, both at 70%.
 * The video frame is drawn only where it shows past the image, with its play mark moved into
 * the visible corner, so the icon reads as "images and videos".
 */
export const allShots = (
	<SVG
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		style={ { fill: 'none' } }
		stroke="currentColor"
		strokeWidth="1.5"
	>
		<Path d="M10.4 8.65V4.275C10.4 3.792 10.792 3.4 11.275 3.4H19.675C20.158 3.4 20.55 3.792 20.55 4.275V12.675C20.55 13.158 20.158 13.55 19.675 13.55H15.3" />
		<Path
			d="M15.475 8.825L18.45 7.075L15.475 5.325V8.825Z"
			fill="currentColor"
			stroke="none"
		/>
		{ /* core image, scaled 0.7; the stroke is widened so it stays 1.5 after scaling. */ }
		<G transform="translate(0.075 7.075) scale(0.7)" strokeWidth="2.143">
			<Path d="M19.25 14.2513L16.0707 11.0707C16.0317 11.0317 15.9683 11.0317 15.9293 11.0707L12.0584 14.9416C12.0245 14.9755 11.9713 14.9806 11.9316 14.9537L8.65791 12.7392C8.62313 12.7156 8.57736 12.7163 8.54332 12.7409L4.75 15.4808M19.25 14.2513V6C19.25 5.30964 18.6904 4.75 18 4.75H6C5.30964 4.75 4.75 5.30964 4.75 6V15.4808M19.25 14.2513V18C19.25 18.6904 18.6904 19.25 18 19.25H6C5.30964 19.25 4.75 18.6904 4.75 18V15.4808" />
		</G>
	</SVG>
);

/** The play mark from core's `video` icon, cropped to fill its box, for the play badge on recordings. */
export const playMark = (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="7 7 10 10">
		<Path d="M10 15L15 12L10 9V15Z" fill="currentColor" />
	</SVG>
);
