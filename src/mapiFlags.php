<?php
declare(strict_types=1);

/*
 * 	Flag definitions
 *
 *	@package	sync*gw
 *	@subpackage	MAPI over HTTP support
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\mapi;

class mapiFlags {

	// [MS-OXOABK] 2.2.2.1 PidTagContainerFlags
	const AB_FLAGS					= [
		'Recipients'				=> 0x00000001,	// container holds Address Book objects.
		'SubContainer'				=> 0x00000002,	// container holds child containers.
		'Unmodifiable'				=> 0x00000008,	// It is not possible to add or remove Address Book objects
													// from the container.
	];

	// [MS-OXCRPC] 2.2.2.2.15 AUX_CLIENT_CONTROL Auxiliary Block Structure
	const CLIENT_CONTROL			= [
		'EnablePerfSendToServer'	=> 0x00000001,	// Client MUST start sending performance information to server
		'EnableCompression'			=> 0x00000004,	// Client MUST Compress information up to the server
		'EnableHTTPTunneling'		=> 0x00000008,	// Client MUST use RPC over HTTP if configured
		'EnablePerfSendCGData'		=> 0x00000010,	// Client MUST include performance data of the client that is
													// Objectmunicating with the directory service
	];

	// [MS-OXNPSI] 2.2.1.3 Display Type Values
	// [MS-OXOABK] 2.2.3.12 PidTagDisplayTypeEx
	const DISPLAY_TYPE 				= [
		'MailUser'					=> 0x00000000,	// A typical messaging user.
		'DistributionList'			=> 0x00000001,	// A distribution list.
		'Forum'						=> 0x00000002,	// A forum, such as a bulletin board service or a public or shared folder.
		'Agent'						=> 0x00000003,	// An automated agent, such as Quote-Of-The-Day or a weather chart display.
		// 'Organization'			=> 0x00000004,	// An Address Book object defined for a large group, such as helpdesk,
													// accounting, coordinator, or department. Department objects usually have
													// this display type.
		'PrivateDistributionList'	=> 0x00000005,	// A private, personally administered distribution list.
		'RemoteMailUser'			=> 0x00000006,	// An Address Book object known to be from a foreign or remote messaging
													// system.
		'Container'					=> 0x00000100,	// An address book hierarchy table container.
		// 'Template'				=> 0x00000101,	// A display template object.
		// 'AddressTemplate'		=> 0x00000102,	// An address creation template.
		// 'Search'					=> 0x00000200,	// A search template.

		'RemoteDisplay'				=> 0x80000000,	// is the remote display type
		'Sharing'					=> 0x40000000,	// the mailbox server supports sharing to the entity
		'Remote'					=> 0x00001000,	// display type of an Address Book object in the remote forest
		// 'Local'					=> 0x00000000,	// display type of an Address Book object in the messaging user's
		//											// local forest
		'Room'						=> 0x00000007,	// conference room.
		'Eequipment'				=> 0x00000008,	// Equipment.
		'SecurityDistributionList'	=> 0x00000009,	// distribution list used as a security group on the server.
	];

	// [MS-OXCRPC] 2.2.2.2.17 AUX_EXORGINFO Auxiliary Block Structure
	const EXORGINFO			 		= [
		'PublicFolderEnabled'		=> 0x00000001,	// Organization has public folders
		'UseAutoDiscover' 			=> 0x00000002, 	// The client SHOULD configure public folders using
													// the Autodiscover Protocol
	];

	// [MS-OXCPRPT]		2.2.12.1 RopGetPropertyIdsFromNames ROP Request Buffer
	const GET_PROP_IDS		 		= [
		'null'						=> 0x00,
		'Create'					=> 0x02, 		// a new entry be created for each named property that
													// is not found in the existing mapping table
	];

	// [MS-OXCRPC] 2.2.2.1 RPC_HEADER_EXT Structure
	const HEADER		 			= [
		'Compressed'				=> 0x0001,	// The data that follows the RPC_HEADER_EXT structure is compressed
		'NoXORMagic'				=> 0x0002,	// The data following the RPC_HEADER_EXT structure has been obfuscated.
		'Last'						=> 0x0004,	// No other RPC_HEADER_EXT structure follows the data of the current RPC_HEADER_EXT
		'null'						=> 0x0000,
	];

	// [MS-OXNSPI] 2.2.1.12 NspiGetSpecialTable Flags
	const NSPI_TAB		  			= [
		'AddressCreationTemplates'	=> 0x00000002,	// Specifies that the server MUST return the table of the
													// available address creation templates. Specifying this
													// flag causes the server to ignore the UnicodeStrings flag.
		'UnicodeString'			=> 0x00000004,	// Specifies that the server MUST return all strings as
													// Unicode representations rather than as multibyte strings
													// in the client's code page.
	];

	// [MS-OXCSTOR] 2.2.1.1.1 RopLogon ROP Request Buffer
	const LOGON				 		= [
		'NoFlags'					=> 0x00,
		'Private'					=> 0x01, 		// This flag is set for logon to a private mailbox
													// and is not set for logon to public folders
		'Undercover'				=> 0x02,		// This flag is ignored by the server
		'Ghosted'					=> 0x04,		// This flag is ignored by the server
		'SpoolerProcess'			=> 0x08,		// This flag is ignored by the server
	];

	// [MS-OXCSTOR] 2.2.1.1.1 RopLogon ROP Request Buffer
	const OPEN				 		= [
		'NoFlags'					=> 0x00000000,
		'UseAdminPrivilege'			=> 0x00000001,	// A request for administrative access to the mailbox
		'Public'					=> 0x00000002,	// A request to open a public folders message store.
													// This flag MUST be set for public logons
		'HomeLogin'					=> 0x00000004,	// This flag is ignored
		'TakeOwnership'				=> 0x00000008,	// This flag is ignored
		'AlternateServer'			=> 0x00000100,	// Requests a private server to provide an alternate
													// public server
		'IgnoreHomeMDB'				=> 0x00000200,	// This flag is used only for public logons
		'NoMail'					=> 0x00000400,	// This flag is ignored
		'UsePerMDBRepliedMapping'	=> 0x01000000,	// For a private-mailbox logon
		'SupportProgress'			=> 0x20000000,	// Indicates that the client supports asynchronous
													// processing of RopSetReadFlags
	];

	// [MS-OXCFOLD]		2.2.1.1.1 RopOpenFolder ROP Request Buffer
	const OPEN_MOD					= [
		'Exists'					=> 0x00,		// opens an existing folder
		'SoftDelete'				=> 0x04,		// opens either an existing folder or a soft-deleted folder
	];

	// [MS-OXCMAPIHTTP] 2.2.5.7.1 GetProps Request Type Request Body
	const PROP						= [
		'null'						=> 0x00000000,
		'Skip'						=> 0x00000001,
		'EPHId'						=> 0x00000002,
	];

	// [MS-OXCSTOR] 2.2.1.1.3 RopLogon ROP Success Response Buffer for Private Mailboxr
	const RESPONSE			 		= [
		'Fixed'						=> 0x01,	// This bit MUST be set and MUST be ignored by the client
		'OwnerRight'				=> 0x02,	// The user has owner permission on the mailbox
		'SendAsRigth'				=> 0x04,	// The user has the right to send mail from the mailbox
		'OOF'						=> 0x10,	// The Out of Office (OOF) state is set on the mailbox
	];

	// [MS-OXCRPC] 3.1.4.1 EcDoConnectEx Method (Opnum 10)
	const USER			 			= [
		'User'						=> 0x00000000,	// Requests connection without administrator privilege
		'Admin'						=> 0x00000001, 	// Requests administrator behavior, which causes the server to
													// check that the user has administrator privilege
		'Folder'					=> 0x00008000,	// If this flag is not passed and the client version (as specified
													// by the rgwClientVersion parameter) is less than 12.00.0000.000
													// and no public folders are configured within the messaging system,
													// the server MUST fail the connection attempt with Error code
													// ecClientVerDisallowed
	];

}
