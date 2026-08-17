<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<!doctype html>
<html>

<head>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<style>
	img {
		border: none;
		-ms-interpolation-mode: bicubic;
		max-width: 100%;
	}

	body {
		background-color: #f6f6f6;
		width: 100%;
		font-family: sans-serif;
		-webkit-font-smoothing: antialiased;
		font-size: 14px;
		line-height: 1.4;
		margin: 0;
		padding: 0;
		-ms-text-size-adjust: 100%;
		-webkit-text-size-adjust: 100%;
	}

	/* Set a max-width, and make it display as block so it will automatically stretch to that width, but will also shrink down on a phone or something */
	.ablocks-container {
		display: block;
		margin: 0 auto !important;
		/* makes it centered */
		max-width: 580px;
		padding: 10px;
		width: 580px;
		background: white;
	}

	/* This should also be a block element, so that it will fill 100% of the .ablocks-container */
	.ablocks-content {
		box-sizing: border-box;
		display: block;
		margin: 0 auto;
		max-width: 580px;
		padding: 45px;
	}

	/* HEADER, FOOTER, MAIN */
	.ablocks-main {
		background: #ffffff;
		border-radius: 3px;
		width: 100%;
	}

	.ablocks-wrapper {
		box-sizing: border-box;
	}
	.ablocks-wrapper p {
		font-size: 14px;
		margin-bottom: 10px;
	}
	.ablocks-wrapper .ablocks-entry-button {
		display: flex;
		column-gap: 15px;
	}

	.ablocks-content-block {
		padding-bottom: 10px;
		padding-top: 10px;
	}

	.ablocks-footer {
		clear: both;
		margin-top: 10px;
		width: 100%;
		margin-top: 30px;
		font-size: 15px;
	}

	.ablocks-footer p,
	.ablocks-footer span,
	.ablocks-footer a {
		color: #999999;
	}

	/* TYPOGRAPHY */
	h1,
	h2,
	h3,
	h4,
	h5,
	h6 {
		color: #000000;
		font-family: sans-serif;
		font-weight: 500;
		line-height: 1.4;
		margin: 0;
		margin-bottom: 10px;
	}

	h1 {
		font-size: 35px;
	}

	p,
	ul,
	ol {
		font-family: sans-serif;
		font-size: 14px;
		font-weight: normal;
		margin: 0;
		margin-bottom: 15px;
	}

	p li,
	ul li,
	ol li {
		list-style-position: inside;
		margin-left: 5px;
	}

	a {
		color: #3498db;
		text-decoration: underline;
	}

	/* BUTTONS */

	.ablocks-btn-primary,
	.ablocks-btn-secondary {
		box-sizing: border-box;
		display: inline-block;
		text-decoration: none;
		font-size: 14px;
		padding: 12px 30px;
		background: #7B68EE;
		color: #FFFFFF !important;
		border-radius: 6px;
		font-weight: normal;
	}
	.ablocks-btn-secondary {
		background: #EAEBEE;
		color: #0A083A !important;
	}

	h5.ablocks-main-heading {
		font-size: 21px;
		margin-bottom: 25px;
	}

	/* OTHER STYLES THAT MIGHT BE USEFUL */
	.ablocks-last {
		margin-bottom: 0;
	}

	.ablocks-first {
		margin-top: 0;
	}

	.ablocks-align-center {
		text-align: center;
	}

	.ablocks-align-right {
		text-align: right;
	}

	.ablocks-align-left {
		text-align: left;
	}

	.ablocks-clear {
		clear: both;
	}

	.ablocks-mt0 {
		margin-top: 0;
	}

	.ablocks-mb0 {
		margin-bottom: 0;
	}

	.ablocks-preheader {
		color: transparent;
		display: none;
		height: 0;
		max-height: 0;
		max-width: 0;
		opacity: 0;
		overflow: hidden;
		mso-hide: all;
		visibility: hidden;
		width: 0;
	}

	.ablocks-powered-by a {
		text-decoration: none;
	}

	hr {
		border: 0;
		border-bottom: 1px solid #f6f6f6;
		margin: 20px 0;
	}

	.ablocks-ql-align-center {
		text-align: left;
	}
	.ablocks-ql-align-center {
		text-align: center;
	}
	.ablocks-ql-align-right {
		text-align: right;
	}
	.ablocks-ql-align-justify {
		text-align: justify;
	}


	ol li.ablocks-ql-indent-1 {
		counter-increment: list-1
	}

	ol li.ablocks-ql-indent-1:before {
		content: counter(list-1,lower-alpha) ". "
	}

	ol li.ablocks-ql-indent-1 {
		counter-reset: list-2 list-3 list-4 list-5 list-6 list-7 list-8 list-9
	}

	ol li.ablocks-ql-indent-2 {
		counter-increment: list-2
	}

	ol li.ablocks-ql-indent-2:before {
		content: counter(list-2,lower-roman) ". "
	}

	ol li.ablocks-ql-indent-2 {
		counter-reset: list-3 list-4 list-5 list-6 list-7 list-8 list-9
	}

	ol li.ablocks-ql-indent-3 {
		counter-increment: list-3
	}

	ol li.ablocks-ql-indent-3:before {
		content: counter(list-3,decimal) ". "
	}

	ol li.ablocks-ql-indent-3 {
		counter-reset: list-4 list-5 list-6 list-7 list-8 list-9
	}

	ol li.ablocks-ql-indent-4 {
		counter-increment: list-4
	}

	ol li.ablocks-ql-indent-4:before {
		content: counter(list-4,lower-alpha) ". "
	}

	ol li.ablocks-ql-indent-4 {
		counter-reset: list-5 list-6 list-7 list-8 list-9
	}

	ol li.ablocks-ql-indent-5 {
		counter-increment: list-5
	}

	ol li.ablocks-ql-indent-5:before {
		content: counter(list-5,lower-roman) ". "
	}

	ol li.ablocks-ql-indent-5 {
		counter-reset: list-6 list-7 list-8 list-9
	}

	ol li.ablocks-ql-indent-6 {
		counter-increment: list-6
	}

	ol li.ablocks-ql-indent-6:before {
		content: counter(list-6,decimal) ". "
	}

	ol li.ablocks-ql-indent-6 {
		counter-reset: list-7 list-8 list-9
	}

	ol li.ablocks-ql-indent-7 {
		counter-increment: list-7
	}

	ol li.ablocks-ql-indent-7:before {
		content: counter(list-7,lower-alpha) ". "
	}

	ol li.ablocks-ql-indent-7 {
		counter-reset: list-8 list-9
	}

	ol li.ablocks-ql-indent-8 {
		counter-increment: list-8
	}

	ol li.ablocks-ql-indent-8:before {
		content: counter(list-8,lower-roman) ". "
	}

	ol li.ablocks-ql-indent-8 {
		counter-reset: list-9
	}

	ol li.ablocks-ql-indent-9 {
		counter-increment: list-9
	}

	ol li.ablocks-ql-indent-9:before {
		content: counter(list-9,decimal) ". "
	}

	.ablocks-ql-indent-1:not(.ablocks-ql-direction-rtl) {
		padding-left: 3em
	}

	li.ablocks-ql-indent-1:not(.ablocks-ql-direction-rtl) {
		padding-left: 4.5em
	}

	.ablocks-ql-indent-1.ablocks-ql-direction-rtl.ablocks-ql-align-right {
		padding-right: 3em
	}

	li.ablocks-ql-indent-1.ablocks-ql-direction-rtl.ablocks-ql-align-right {
		padding-right: 4.5em
	}

	.ablocks-ql-indent-2:not(.ablocks-ql-direction-rtl) {
		padding-left: 6em
	}

	li.ablocks-ql-indent-2:not(.ablocks-ql-direction-rtl) {
		padding-left: 7.5em
	}

	.ablocks-ql-indent-2.ablocks-ql-direction-rtl.ablocks-ql-align-right {
		padding-right: 6em
	}

	li.ablocks-ql-indent-2.ablocks-ql-direction-rtl.ablocks-ql-align-right {
		padding-right: 7.5em
	}

	.ablocks-ql-indent-3:not(.ablocks-ql-direction-rtl) {
		padding-left: 9em
	}

	li.ablocks-ql-indent-3:not(.ablocks-ql-direction-rtl) {
		padding-left: 10.5em
	}

	.ablocks-ql-indent-3.ablocks-ql-direction-rtl.ablocks-ql-align-right {
		padding-right: 9em
	}

	li.ablocks-ql-indent-3.ablocks-ql-direction-rtl.ablocks-ql-align-right {
		padding-right: 10.5em
	}

	.ablocks-ql-indent-4:not(.ablocks-ql-direction-rtl) {
		padding-left: 12em
	}

	li.ablocks-ql-indent-4:not(.ablocks-ql-direction-rtl) {
		padding-left: 13.5em
	}

	.ablocks-ql-indent-4.ablocks-ql-direction-rtl.ablocks-ql-align-right {
		padding-right: 12em
	}

	li.ablocks-ql-indent-4.ablocks-ql-direction-rtl.ablocks-ql-align-right {
		padding-right: 13.5em
	}

	.ablocks-ql-indent-5:not(.ablocks-ql-direction-rtl) {
		padding-left: 15em
	}

	li.ablocks-ql-indent-5:not(.ablocks-ql-direction-rtl) {
		padding-left: 16.5em
	}

	.ablocks-ql-indent-5.ablocks-ql-direction-rtl.ablocks-ql-align-right {
		padding-right: 15em
	}

	li.ablocks-ql-indent-5.ablocks-ql-direction-rtl.ablocks-ql-align-right {
		padding-right: 16.5em
	}

	.ablocks-ql-indent-6:not(.ablocks-ql-direction-rtl) {
		padding-left: 18em
	}

	li.ablocks-ql-indent-6:not(.ablocks-ql-direction-rtl) {
		padding-left: 19.5em
	}

	.ablocks-ql-indent-6.ablocks-ql-direction-rtl.ablocks-ql-align-right {
		padding-right: 18em
	}

	li.ablocks-ql-indent-6.ablocks-ql-direction-rtl.ablocks-ql-align-right {
		padding-right: 19.5em
	}

	.ablocks-ql-indent-7:not(.ablocks-ql-direction-rtl) {
		padding-left: 21em
	}

	li.ablocks-ql-indent-7:not(.ablocks-ql-direction-rtl) {
		padding-left: 22.5em
	}

	.ablocks-ql-indent-7.ablocks-ql-direction-rtl.ablocks-ql-align-right {
		padding-right: 21em
	}

	li.ablocks-ql-indent-7.ablocks-ql-direction-rtl.ablocks-ql-align-right {
		padding-right: 22.5em
	}

	.ablocks-ql-indent-8:not(.ablocks-ql-direction-rtl) {
		padding-left: 24em
	}

	li.ablocks-ql-indent-8:not(.ablocks-ql-direction-rtl) {
		padding-left: 25.5em
	}

	.ablocks-ql-indent-8.ablocks-ql-direction-rtl.ablocks-ql-align-right {
		padding-right: 24em
	}

	li.ablocks-ql-indent-8.ablocks-ql-direction-rtl.ablocks-ql-align-right {
		padding-right: 25.5em
	}

	.ablocks-ql-indent-9:not(.ablocks-ql-direction-rtl) {
		padding-left: 27em
	}

	li.ablocks-ql-indent-9:not(.ablocks-ql-direction-rtl) {
		padding-left: 28.5em
	}

	.ablocks-ql-indent-9.ablocks-ql-direction-rtl.ablocks-ql-align-right {
		padding-right: 27em
	}

	li.ablocks-ql-indent-9.ablocks-ql-direction-rtl.ablocks-ql-align-right {
		padding-right: 28.5em
	}
	</style>
</head>

<body>
