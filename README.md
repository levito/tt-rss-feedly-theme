tt-rss-feedly-theme
===================

Feedly theme for [Tiny Tiny RSS](https://tt-rss.org).

If you are using an older version of TT-RSS, have a look at the branches.

For the best experience, use a current browser. IE9 and older versions are not supported.

This theme is tested in recent versions of Chromium based browsers (Chrome, Edge, Brave, Vivaldi, Opera) on a regular basis and should work fine in Firefox as well.

## Installation

**Prerequisites:** Running instance of TT-RSS

Install steps (If you did not find the description on the [TT-RSS Homepage](https://git.tt-rss.org/git/tt-rss/wiki/Themes)):

1. Download the ZIP-File: `wget https://github.com/levito/tt-rss-feedly-theme/archive/master.zip`
2. Unzip the ZIP-File: `unzip master.zip`
3. Change into the newly created folder: `cd tt-rss-feedly-theme-master`
4. Copy the relevant files into your TT-RSS folder: `cp -r feedly* [TT-RSS_Home]/themes.local`
5. Go into your TT-RSS preferences and select the feedly-theme.

## Configuration

There are different color schemes available. If you choose the `auto` variants, your OS/browser will decide whether to use the light or dark color scheme.

You can configure the post content font size and the general UI spacing by using the `Customize` button in the TT-RSS settings and adding and adjusting this chunk of CSS code:

```css
:root {
  --base-spacing: 30px;
  --font-size-post: 14px;
}
```

`feedly_cozy` and `feedly_compact` are two examples preconfigured with different font-size and spacing values. They might be removed in the future in favor of using TT-RSS custom styles for this kind of adjustment.

## Development

Don't make direct changes to the CSS files on root level. They are generated from `src`.

In order to generate the CSS files, you will need node.js and npm installed.

1. Run `npm install` to install dependencies
2. Run `npm start` to watch `src` and compile on changes

## Screenshots

![grouped](https://raw.github.com/levito/tt-rss-feedly-theme/master/screenshots/feedly-grouped.png?190111)

![expandable](https://raw.github.com/levito/tt-rss-feedly-theme/master/screenshots/feedly-expandable.png?190111)

![expanded](https://raw.github.com/levito/tt-rss-feedly-theme/master/screenshots/feedly-expanded.png?190111)

![cards (expandable grid)](https://raw.github.com/levito/tt-rss-feedly-theme/master/screenshots/feedly-cards.png?210404)

![cards (expanded grid)](https://raw.github.com/levito/tt-rss-feedly-theme/master/screenshots/feedly-grid.png?210404)

![traditional](https://raw.github.com/levito/tt-rss-feedly-theme/master/screenshots/feedly-traditional.png?190111)

![traditional, wide, hidden sidebar](https://raw.github.com/levito/tt-rss-feedly-theme/master/screenshots/feedly-traditional-widescreen.png?190111)

![preferences + layer](https://raw.github.com/levito/tt-rss-feedly-theme/master/screenshots/feedly-night.png?190111)

Licensed under the WTFPL
