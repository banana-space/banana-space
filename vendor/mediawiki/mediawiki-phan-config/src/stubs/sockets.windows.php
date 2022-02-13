<?php

/**
 * php extension sockets under windows does not include some constants, make phan happy with a stub
 */
namespace {
	const MSG_EOF = 512;
	const MSG_EOR = 128;
}
