<?php
declare(strict_types=1);

/*
 * 	Constant definitions
 *
 *	@package	sync*gw
 *	@subpackage	MAPI over HTTP support
 *	@copyright	(c) 2008 - 2026 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\mapi;

class mapiDefs {

	// [MS-OXOABK] 2.2.2.4 PidTagAddressBookIsMaster
	const AB_MASTER					= [
		'true'						=> 0x01,
		'false'						=> 0x00,
	];

	// [MS-OXCMAPIHTTP] 2.2.1.7 AddressBookPropertyRow Structure
	const AB_ROW_TYP		 		= [
		'Implied'					=> 0x00,		// contains either an AddressBookPropertyValue structure or an
													// AddressBookTypedPropertyValue structure
		'Flagged'					=> 0x01,		// contains either an AddressBookFlaggedPropertyValue structure
													// or an AddressBookFlaggedPropertyValueWithType structure
	];

	// [MS-UCODEREF] 2.2.1 Supported Codepage in Windows
	const CODEPAGE					= [
		'UTF-16LE'					=> 1200,
		'ISO-8859-1'				=> 1252,
		'ANSI'						=> 1250,
	];

	// [MS-OXCDATA] 2.11.1 Property Data Types
	// [MS-OXCDATA] 2.11.1.1 COUNT Data Type Values
	const DATA_TYP		 			= [
		'I2'						=> 0x0002,	// PtypInteger16	2 bytes; a 16-bit integer
		'I4'						=> 0x0003,	// PtypInteger32	4 bytes; a 32-bit integer
		'I8'						=> 0x0014,	// PtypInteger64	8 bytes; a 64-bit integer
		'B'							=> 0x000B,	// PtypBoolean		1 byte; Restrictioned to 1 or 0
		'S'							=> 0x001F,	// PtypString		Variable size; a string of Unicode characters in
												// 					UTF-16LE format encoding with terminating null
												// 					character (0x0000)
		'A'							=> 0x001E,	// PtypString8		Variable size; a string of multibyte characters in
												// 					externally specified encoding with terminating null
												// 					character (single 0 byte).
		'T'							=> 0x0040,	// PtypTime			8 bytes; a 64-bit integer representing the number of
												// 					100-nanosecond intervals since January 1, 1601
		'G'							=> 0x0048,	// PtypGuid			16 bytes; a GUID with Data1, Data2, and Data3 fields
												// 					in little-endian format
		'H'							=> 0x0102,	// PtypBinary				Variable size; a COUNT field followed by that many bytes
		'M_I2'						=> 0x1002,	// PtypMultipleInteger16 	Variable size; a COUNT field followed by that many values
		'M_I4'						=> 0x1003,	// PtypMultipleInteger32	Variable size; a COUNT field followed by that many values
		'M_I8'						=> 0x1014,	// PtypMultipleInteger64	Variable size; a COUNT field followed by that many values
		'M_S'						=> 0x101F,	// PtypMultipleString		Variable size; a COUNT field followed by that many values
		'M_A'						=> 0x101E,	// PtypMultipleString8		Variable size; a COUNT field followed by that many values
		'M_T'						=> 0x1040,	// PtypMultipleTime			Variable size; a COUNT field followed by that many values
		'M_G'						=> 0x1048,	// PtypMultipleGuid			Variable size; a COUNT field followed by that many values
		'M_H'						=> 0x1102,	// PtypMultipleBinary		Variable size; a COUNT field followed by that many values

		# -----

		'Float32'					=> 0x0004,	// PtypFloating32	4 bytes; a 32-bit floating point number
		'Float64'					=> 0x0005,	// PtypFloating64	8 bytes; a 64-bit floating point number
		'Currency'					=> 0x0006,	// PtypCurrency		8 bytes; a 64-bit signed, scaled integer
												// 					representation of a decimal Currency value,
												// 					with four places to the right of the decimal point
		'FloatTime'					=> 0x0007,	// PtypFloatingTime	8 bytes; a 64-bit floating point number in which the
												// 					whole number part represents the number of days since
												// 					December 30, 1899, and the fractional part represents
												// 					the fraction of a day since midnight
		'Error'						=> 0x000A,	// PtypErrorCode	4 bytes; a 32-bit integer encoding Error information
												// 					as specified in section 2.4.1
		'ServerId'					=> 0x00FB,	// PtypServerId		Variable size; a 16-bit COUNT field followed by a
												// 					structure as specified in section 2.11.1.4.
		'Restriction'				=> 0x00FD,	// PtypRestrictionion	Variable size; a byte array representing one or more
												// 					Restrictionion structures as specified in section 2.12
		'RuleAction'				=> 0x00FE,	// PtypRuleAction	Variable size; a 16-bit COUNT field followed by that
												// 					many rule action structures, as specified in
												// 					[MS-OXORULE] section 2.2.5
		'Unspecific'				=> 0x0000,	// PtypUnspecified	Any: this property type value matches any type; a server
												// 					MUST return the actual type in its response. Servers MUST
												// 					NOT return this type in response to a client request other
												// 					than NspiGetIDsFromNames or the RopGetPropertyIdsFromNames
												// 					ROP request ([MS-OXCROPS] section 2.2.8.1)
		'null'						=> 0x0001,	// Ptypnull			This property is a placeholder
		'Object'					=> 0x000D,	// PtypObject		The property value is a Component Object Model (Object) object,
												// 					as specified in section 2.11.1.5.
		'M_Float32'					=> 0x1004,	// PtypMultipleFloating32	Variable size; a COUNT field followed by that many values
		'M_Float64'					=> 0x1005,	// PtypMultipleFloating64	Variable size; a COUNT field followed by that many values
		'M_Currency'				=> 0x1006,	// PtypMultipleCurrency		Variable size; a COUNT field followed by that many values
		'M_FloatTime'				=> 0x1007,	// PtypMultipleFloatingTime	Variable size; a COUNT field followed by that many values
	];

	// internal Complex data types
	const DATA_TYP_COMPLEX			= [
		'XA'						=> '_getRPC',
		'XB'						=> '_getRestriction',
		'XC'						=> '_getTaggedProperty',
		'XD'						=> '_getPropertyRow',
	];

	// [MS-OXNPSI] 2.2.9 EntryIDs
	const ENTRY_ID_TYP				= [
		// 'MIN_ID'
		'EphId'						=> 0x87,		// identifies a specific object in the address book
		'PermId'					=> 0x00,		// identifies a specific object in the address book
	];

	// [MS-OXCDATA] 2.4.2 Property Error Codes
	// [MS-OXCDATA] 2.4 Error Codes
	const ERR_CODE			 		= [
		'Success'					=> 0x00000000,	// Ok

		'NotFound'					=> 0x8004010F,	// On get, indicates that the property or column has no
													// value for this object
		'ErrorsReturned'			=> 0x00040380,	// A request involving multiple properties failed for one
													// or more individual properties, while succeeding overall
		'ServerBusy'				=> 0x00000480,	// The server is too busy to Complete an operation

		// 'GeneralFailure'			=> 0x80004005,	// operation failed for an unspecified reason
		// 'OutOfMemory'			=> 0x8007000E,	// Not enough memory was available to complete the operation
		// 'InvalidParameter'		=> 0x80070057,	// invalid parameter was passed to a remote procedure call
		// 'NoInterface'			=> 0x80004002,	// requested interface is not supported
		'AccessDenied'				=> 0x80070005,	// caller does not have sufficient access rights to
													// perform the operation
	];

	// [MS-OXCMAPIHTTP] 2.2.1.5 AddressBookFlaggedPropertyValue Structure
	// [MS-OXCDATA] 2.11.5 FlaggedPropertyValue Structure
	const FLAGGED_TYP				= [
		'Implied'					=> 0x00,		// The PropertyValue field will be a PropertyValue structure containing
													// a value Compatible with the property type implied by the context.
		'NoExist'					=> 0x01,		// The PropertyValue field is not present.
		'Error'						=> 0xff,		// property Error code, as specified in section 2.4.2,
	];

	// [MS-OXCDATA] 2.2.1.1 Folder ID Structure (6 byte)
	// [MS-OXOSFLD] Special Folders Protocol
	const GLOBAL_COUNTER			= [
		'Root'						=> '000000000001',	// Contains folders for managing IPM messages
		'Action'					=> '000000000002',
		'Spooler'					=> '000000000003',
		'Shortcuts'					=> '000000000004',
		'Search'					=> '000000000005',	// Contains folders for managing search results
		'Views'						=> '000000000006',	// Contains folders for managing views for a particular user
		'Common'					=> '000000000007',	// Contains folders for managing views for the message store
		'Schedule'					=> '000000000008',
		'InterPersonal'				=> '000000000009',
		'Sent'						=> '00000000000a',	// Contains IPM messages that have been sent
		'Deleted'					=> '00000000000b',	// Contains IPM messages that are marked for deletion
		'Outbox'					=> '00000000000c',	// Contains outgoing IPM messages
		'Inbox'						=> '00000000000d',	// Contains incoming messages for a particular message class

		'Drafts'					=> '00000000000e',
		'Calendar'					=> '00000000000f',
		// 'Journal'				=> '000000000010',
		'Notes'						=> '000000000011',
		'Task'						=> '000000000012',
		'Contact'					=> '000000000013',
		// 'FixContact'				=> '000000000014',
		// 'IMContact'				=> '000000000015',
		// 'GAL'					=> '000000000016',
		'Spam'						=> '000000000017',
		'SyncProblems'				=> '000000000019',
		'Conflicts'					=> '00000000001a',
		'LocalError'				=> '00000000001b',
		'ServerError'				=> '00000000001c',
		'Config'					=> '00000000001d',
	];

	// [MS-OXNPSI] 2.2.1.7 Permanent Entry ID GUID
	const GUID						= [
		'NPSI'						=> 'C840A7DC-42C0-1A10-B9B4-000882E12F2B',
		'Server'					=> 'E451EB24-ECFD-4A9B-968F-8ACFD806E00C',
		'MailBox'					=> '7AA9F315-E0BB-43C2-C2A6-06A4A5E892C2',
		'AddresssBook'				=> '01000001-0001-0000-E498-6E988FB9362D',
		'Replica'					=> '00000001-7F26-EDF8-2AB3-7ACEB5E3A26D',
		'MessageDB'					=> '74314067-726F-6D6D-2E66-726901000000',
		'User'						=> '81000001-xxxx-0000-DB2B-99C6C02494C7',

		'UserFolder'				=> '00000001-18A5-6F7B-DCBC-1EEA57563CD0',
		'Inbox'						=> '00000001-18A5-6F7B-DCBC-1EEA57563CD0',
	##	'Calendar'					=> '00000000-15F3-A97A-BBE0-C243A6C2A406',
	];

	// [MS-OXCDATA] 2.6 Property Name Structures
	const KIND						= [
		'LID'						=> 0x00,		// The property is identified by the LID field
		'Name'						=> 0x01,		// The property is identified by the Name field
		'NoName'					=> 0xff,		// The property does not have an associated PropertyName field
	];

	// [MS-LICS] 2.2 LCID Structure
	const LCID						= [
		'de-DE'						=> 0x0407,
		'en-US'						=> 0x0409,
	];

 	// [MS-OXPROPS] Property Long ID / PSETID_Common
	const LID			 			= [
		'InternetAccountName'		=> 0x8580,	// 2.152 	PidLidInternetAccountName
												// 			the user-visible email account name through which the email
												//			message is sent
		'InternetAccountStamp'		=> 0x8581,	// 2.153 	PidLidInternetAccountStamp
												//			the email account ID through which the email message is sent.

	];

	// [MS-OXNSPI] 3.1.4.6 Object Identity
	// [MS-OXNSPI] 2.2.9 EntryIDs
	// [MS-OXNSPI] 2.2.9.1 MinimalEntryID
	// [MS-OXOABK] 2.2.2.3 PidTagAddressBookContainerId (Minimal Entry IDs)
	const MIN_ID	        		= [
		'Specific01'				=> 0x00000001,	// values less than 0x00000010 are used by clients as signals
		'Specific02'				=> 0x00000002,	// to trigger specific behaviors in specific NSPI methods.
    	'Specific03'				=> 0x00000003,
    	'Specific04'				=> 0x00000004,
    	'Specific05'				=> 0x00000005,
    	'Specific06'				=> 0x00000006,
    	'Specific07'				=> 0x00000007,
    	'Specific08'				=> 0x00000008,
    	'Specific09'				=> 0x00000009,
    	'Specific0A'				=> 0x0000000a,
    	'Specific0B'				=> 0x0000000b,
    	'Specific0C'				=> 0x0000000c,
    	'Specific0D'				=> 0x0000000d,
    	'Specific0E'				=> 0x0000000e,
    	'Specific0F'				=> 0x0000000f,
    	'Specific10'				=> 0x00000010,

    	'Handle01'					=> 0x00000011,	// user handle
    	'Handle02'					=> 0x00000012,
    	'Handle03'					=> 0x00000013,

		'AddressBookContainer'		=> 0x80000000,	// identifies an address book container on an address book server
    	'GlobalAddressList'			=> 0x00000000,	// represents the Global Address List (GAL).
	];

	// [MS-OXNSPI] 2.2.1.9 Ambiguous Name Resolution Minimal Entry IDs
	// Ambiguous name resolution (ANR) Minimal Entry IDs are used to specify the outObjecte of the ANR process
	const MIN_ID_ANR	         	= [
		'Unresolved'				=> 0x00000000,	// The ANR process was unable to map a string to
													// any objects in the address book
		'Ambigious'					=> 0x00000001,	// The ANR process mapped a string to multiple objects
													// in the address book
		'Resolved'					=> 0x00000002,	// The ANR process mapped a string to a single object
	];

	// [MS-OXCPRPT] 2.2.1.7 PidTagObjectType Property
	// [MS-OXOABK] 2.2.3.10 PidTagObjectType
	const OBJ_TYPE			 		= [
		'StoreObject'				=> 0x00000001,	// Store object
		'AddressBookObject'			=> 0x00000002,	// Address Book object
		'Folder'					=> 0x00000003,	// Folder
		'AddressBookContainer'		=> 0x00000004,	// Address book container
		'MessageObj'				=> 0x00000005,	// Message object
		'MailUser'					=> 0x00000006,	// Mail user
		'AttachmentObject'			=> 0x00000007,	// Attachment object
		'DistributionList'			=> 0x00000008,	// Distribution list
	];

	// [MS-OXPROPS] 2 Structures
	const PID			 			= [
		// PidTag
		'Access'						=> 0x0FF4,	// 2.505 	the operations available to the client for the object
		'Account'						=> 0x3A00,	// 2.508	alias of an Address Book object, which is an
													//			alternative name by which the object can be identified.
		'AccessControlListData'			=> 0x3FE0,	// 2.506 	permissions list for a folder
		'AddressBookObjectGuid'			=> 0x8C6D,	// 2.555 	GUID that identifies an Address Book object.
		'AddressBookNetworkAddress'		=> 0x8170,	// 2.553 	a list NETWORKof names by which a server is known to the various
													//			transports in use by the network
		'AddressBookHomeMessageDatabase'=> 0x8006,	// 2.544	DN expressed in the X500 DN format.
		'AddressBookProxyAddresses'		=> 0x800F, 	// 2.565 	alternate email addresses for the Address Book object
		'AddressBookContainerId'		=> 0xFFFD,	// 2.512 	the ID of a container on an NSPI server.
		'AddressBookIsMaster'			=> 0xFFFB,	// 2.545 	true if it is possible to create Address Book objects in that
													//			container, and false otherwise.
		'AddressType'					=> 0x3002,	// 2.576	email address type of a Message object.
		'Anr'							=> 0x360C,	// 2.578 	filter value used in ambiguous name resolution
		'AttachNumber'					=> 0x0E21,	// 2.603	Attachment object within its Message object.
		'BusinessTelephoneNumber'		=> 0x3A08,	// 2.626	primary telephone number of business.
		'CompanyName'					=> 0x3A16,	// 2.639	mail user's Company name.
		'ContainerFlags'				=> 0x3600,	// 2.644 	a bitmask of flags that describe capabilities of an
													//			addressbook container
		'ContentCount'					=> 0x3602,	// 2.646 	the number of rows under the header row
		'ContentUnreadCount'			=> 0x3603,	// 2.648 	the number of rows under the header row that have the PidTagRead
													//			property set to false
		'DepartmentName'				=> 0x3A18,	// 2.672	a name for the department in which the mail user works.
		'Depth'							=> 0x3005,	// 2.673 	the number of nested categories in which a given row is contained.
		'DisplayName'					=> 0x3001,	// 2.676 	display name of the folder.
		'DisplayType'					=> 0x3900,	// 2.679	an integer value that indicates how to display an Address Book
													//			object in a table or as an addressee on a message.
		'DisplayTypeEx'					=> 0x3905,	// 2.680	an integer value that indicates how to display an Address Book
													//			object in a table or as a recipient on a message.
		'EmailAddress'					=> 0x3003,	// 2.681	email address of a Message object.
		'EntryId'						=> 0x0FFF,	// 2.683	information to identify many different types of messaging objects
		'FolderType'					=> 0x3601,	// 2.702 	the type of a folder that includes the Root folder,
													//			Generic folder, and Search folder
		'InstanceKey'					=> 0x0FF6,	// 2.743	object on an NSPI server.
		'MailboxOwnerName'				=> 0x661C,	// 2.778 	display name of the owner of the mailbox
		'MailboxOwnerEntryId'			=> 0x661B,	// 2.777 	the EntryID in the GAL of the owner of the mailbox
		'MessageSize'					=> 0x0E08,	// 2.796 	the size, in bytes, consumed by the Message object on the server
		'ObjectType'					=> 0x0FFE,	// 2.813	type of Server object.
		'OfficeLocation'				=> 0x3A19,	// 2.814	mail user's office location.
		'SmtpAddress'					=> 0x39FE,	// 2.1020	SMTP address of the Message object.
		'ThumbnailPhoto'				=> 0x8C9E,	// 2.1045 	the mail user's photo in .jpg format
		'Title'							=> 0x3A17,	// 2.1046 	mail user's job title.
		'MaximumSubmitMessageSize'		=> 0x666D,	// 2.781 	Maximum size, in kilobytes, of a message that a user is allowed
													//			to submit for transmission to another user
		'LocalCommitTimeMax'			=> 0x670A,	// 2.773 	the time of the most recent message change within the folder
													//			container, excluding messages changed within subfolders
		'DeletedCountTotal'				=> 0x670B,	// 2.669	the total count of messages that have been deleted from a
													// 			folder, excluding messages deleted within subfolders
		'Subfolders'					=> 0x360A,	// 2.1032	whether a folder has subfolders
		'Comment'						=> 0x3004,	// 2.637	comment about the purpose or content of the Address Book object
		'CreationTime'					=> 0x3007,	// 2.654	the time, in UTC, that the object was created
		'LastModificationTime'			=> 0x3008,	// 2.764	the time, in UTC, of the last modification to the object
		'ContainerClass'				=> 0x3613,	// 2.642	describes the type of Message object that a folder contains
		'HierarchyTime'					=> 0x4082,	// 2.721	the time, in UTC, to trigger the client in cached mode to synchronize the folder hierarchy
		'ChangeKey'						=> 0x65E2,	// 2.631	a structure that identifies the last change to the object
		'PredecessorChangeList'			=> 0x65E3,	// 2.867	a value that contains a serialized representation of a PredecessorChangeList structure
		'ParentFolderId'				=> 0x6749,	// 2.859	contains the Folder ID (FID), that identifies the parent folder of the messaging object being synchronized
		'AttributeHidden'				=> 0x10F4,	// 2.611	the hide or show status of a folder
		'AttributeReadOnly'				=> 0x10F6,	// 2.612	Indicates whether an item can be modified or deleted
		'SourceKey'						=> 0x65E0,	// 2.1022	contains an internal global identifier (GID) for this folder or message
		'ParentSourceKey'				=> 0x65E1,	// 2.861	a value on a folder that contains the SourceKey property of the parent folder
		'RemindersOnlineEntryId'		=> 0x36D5,	// 2.912	an EntryID for the Reminders folder
		'AdditionalRenEntryIdsEx'		=> 0x36D9,	// 2.510	an array of blocks that specify the EntryIDs of several special folders
		'IpmDraftsEntryId'				=> 0x36D7,	// 2.752	the EntryID of the Drafts folder
		'IpmContactEntryId'				=> 0x36D1,	// 2.751	the EntryID of the Contacts folder
		'IpmAppointmentEntryId'			=> 0x36D0,	// 2.750	the EntryID of the Calendar folder
		'IpmJournalEntryId'				=> 0x36D2,	// 2.753	the EntryID of the Journal folder
		'IpmNoteEntryId'				=> 0x36D3,	// 2.754	the EntryID of the Notes folder
		'IpmTaskEntryId'				=> 0x36D4,	// 2.755	the EntryID of the Tasks folder
		'AdditionalRenEntryIds'			=> 0x36D8,	// 2.509	indexed entry IDs for several special folders related to conflicts, sync issues, local failures, server failures, junk email and spam
		'FreeBusyEntryIds'				=> 0x36E4,	// 2.705	EntryIDs of the Delegate Information object, the free/busy message of the logged on user, and the folder with the DisplayName property value of "Freebusy Data"

		// MetaTag
		'IdsetGiven'					=> 0x4017,	// {MS_OXCFXICS] 2.2.1.1.1 ICS State Property
		'TagCnsetSeen'					=> 0x6796,	// {MS_OXCFXICS] 2.2.1.1.2 ICS State Property
		'CnsetSeenFAI'					=> 0x67DA,	// {MS_OXCFXICS] 2.2.1.1.3 ICS State Property
		'CnsetRead'						=> 0x67D2,	// {MS_OXCFXICS] 2.2.1.1.4 ICS State Property

		'InternetAccountName'			=> 0x8104,	// see mapiDefs::LID
		'InternetAccountStamp'			=> 0x8105,	// see mapiDefs::LID

		// https://learn.microsoft.com/en-us/office/client-developer/outlook/mapi/outlook-mapi-reference
		'ContactAddressBookFolderName'	=> 0x6613,	// a folder name used for address book entries
		'ServerTypeDisplayName'			=> 0x341D,	// he Unicode string to display in the status bar with this property
		'ServerConnectedIcon'			=> 0x341E,	// the icon displayed in the status bar
		'ServerAccountIcon'				=> 0x341F,	// the icon displayed in the Account Picker
		'RulesTable'					=> 0x3FE1,	// a table with all rules applied to a folder
		'AssociatedContentCount'		=> 0x3617,	// count of items in the associated contents table of the folder
		'InternetArticleNumber'			=> 0x0E23,	// number associated with an item in a message store


		'##0x7C04'						=> 0x7C04,	// ??
		'##0x0E27'						=> 0x0E27,
		'##0x10F5'						=> 0x10F5,
		'##0x0E58'						=> 0x0E58,

	];

	// [MS-OXPROPS] 1.3.2 Commonly Used Property Sets
	const PROPSET					= [
		'PubblicString'				=> '00020329-0000-0000-C000-000000000046',
		'Common'					=> '00062008-0000-0000-C000-000000000046',
		'Contact'					=> '00062004-0000-0000-C000-000000000046',
		'Email'						=> '00020386-0000-0000-C000-000000000046',
		'Calendar'					=> '00062002-0000-0000-C000-000000000046',
		'Meeting'					=> '6ED8DA90-450B-101B-98DA-00AA003F1305',
		'Journal'					=> '0006200A-0000-0000-C000-000000000046',
		'Messaging'					=> '41F28F13-83F4-4114-A584-EEDB5A6B0BFF',
		'Note'						=> '0006200E-0000-0000-C000-000000000046',
		'RSS'						=> '00062041-0000-0000-C000-000000000046',
		'Task'						=> '00062003-0000-0000-C000-000000000046',
		'UnifiedMessaging'			=> '4442858E-A9E3-4E80-B900-317A210CC15B',
		'MAPI'						=> '00020328-0000-0000-C000-000000000046',
		'Sync'						=> '71035549-0739-4DCB-9163-00F0580DBBDF',
		'Sharing'					=> '00062040-0000-0000-C000-000000000046',
		'XML'						=> '23239608-685D-4732-9C55-4C95CB4E8E33',
		'Attachment'				=> '96357F7F-59E1-47D0-99A7-46515C183B54',

		'##Unknown'					=> '08200600-0000-0000-C000-000000000046',
	];

	// [MS-OXNPSI] 2.2.1.10 Table Sort Orders
	const SORT_TYPE		 	 		= [
		'DisplayName'				=> 0x00000000, 	// The table is sorted ascending on the PidTagDisplayName property
		'PhoneticDisplayName'		=> 0x00000003,	// The table is sorted ascending on the
													// PidTagAddressBookPhoneticDisplayName property
		'DisplayName_RO'			=> 0x000003E8,	// The table is sorted ascending on the PidTagDisplayName property
		'DisplayName_W'				=> 0x000003E9,	// The table is sorted ascending on the PidTagDisplayName property
	];

	// [MS-OXCDATA] 2.8.1 PropertyRow Structures
	const VALUE_TYP		 			= [
		'Implied'					=> 0x00,		// The PropertyValue field will be a PropertyValue structure containing
													// a value Compatible with the property type implied by the context.
		'Flagged'					=> 0x01,		// to indicate that there are Errors or some property values are missing
		'Error'						=> 0x0a,		// property Error code, as specified in section 2.4.2,
	];

}
