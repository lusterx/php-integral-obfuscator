<?php

namespace IntegralObfuscator;

defined("TMP_DIR") or exit("TMP_DIR is not defined!\n");

use Exception;
use PhpParser\Error;
use PhpParser\NodeDumper;
use PhpParser\NodeVisitor;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;

/**
 * @author Ammar Faizi <ammarfaizi2@gmail.com>
 * @license MIT
 * @version 0.0.1
 * @package \IntegralObfuscator
 */
final class IntegralObfuscator
{
	/**
	 * @var string
	 */
	private $inputFile;

	/**
	 * @var string
	 */
	private $outputFile;

	/**
	 * @var resource
	 */
	private $outHandle;

	/**
	 * @var string
	 */
	private $inputContent;

	/**
	 * @var string
	 */
	private $sheBang = null;

	/**
	 * @var string
	 */
	private $hash;

	/**
	 * @var mixed
	 */
	private $ast;

	/**
	 * @var string
	 */
	private $key = "abc123";

	/**
	 * @var array
	 */
	private $fx = [];

	/**
	 * @var string
	 */
	private $decryptorName;

	/**
	 * @var string
	 */
	private $md5Key;

	/**
	 * @var string
	 */
	private static $rdl;

	/**
	 * @var array
	 */
	private $internalDecompressor = [];

	/**
	 * @var int
	 */
	private $maxDecompressor;

	/**
	 * @var string
	 */
	private static $intStop = "/*\ec\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0*/";

	/**
	 * @param string $inputFile
	 * @param string $outputFile
	 * @throws \Exception
	 *
	 * Constructor.
	 */
	public function __construct(string $inputFile, string $outputFile)
	{
		$this->inputFile = $inputFile;
		$this->outputFile = $outputFile;

		if (!file_exists($inputFile)) {
			throw new Exception("Input file does not exist: {$inputFile}");
		}

		if (!is_readable($inputFile)) {
			throw new Exception("Input file is not readable: {$inputFile}");
		}

		$this->inputContent = file_get_contents($inputFile);
		$this->hash = sha1($this->inputContent);

		if (!is_string($this->inputContent)) {
			throw new Exception("An error occured when opening the input file: {$inputFile}");
		}

		$this->outHandle = fopen($outputFile, "w");
		if (!is_resource($this->outHandle)) {
			throw new Exception("Cannot open output file: {$outputFile}");
		}

		self::$rdl = range(chr(0), chr(31));
	}

	/**
	 * @param string $sheBang
	 * @return void
	 */
	public function setShebang(string $sheBang): void
	{
		$this->sheBang = $sheBang;
	}

	/**
	 * @param string $key
	 * @return void
	 */
	public function setKey(string $key): void
	{
		$this->key = $key;
	}

	/**
	 * @return void
	 */
	public function execute(): void
	{
		$this->runVisitor();
		if (is_string($this->sheBang)) {
			fwrite($this->outHandle, "#!{$this->sheBang}\n");
		}
		fwrite($this->outHandle, "<?php");
		$this->buildHeader();
		$this->buildInternalFunc();
		$this->buildIntegralInit();
		$this->buildApp();
		$this->buildFooter();
		$this->signHash();
	}

	/**
	 * @return void
	 */
	private function buildHeader(): void
	{
		fwrite($this->outHandle,
"
/**
 * DO NOT EDIT THIS FILE BY HAND!
 *
 * @link https://github.com/ammarfaizi2/php-integral-obfuscator
 * @copyright https://php-obfuscator.teainside.org
 * @license MIT
 * @version 0.0.1
 *
 * std::hd::".sha1($this->key)."
 *
 * handled = SIGINT, SIGTERM, SIGCHLD
 */

#include <php/integral/obf.h>
#include <php/integral/protector.h>
#include <php/integral/internal_opcode.h>
#include <php/integral/intermediate_bytecode.h>

if (function_exists(\"pcntl_signal\")) {
	pcntl_signal(SIGCHLD, SIG_IGN);
}

\$kfk = \"".($this->md5Key = md5($this->key))."\";

/*\0".str_replace("*", "\0", $this->gen(4096, 1))."\0*/");
	}

	/**
	 * @return void
	 */
	private function buildInternalFunc(): void
	{
		$this->fx = [
			"gzinflate" => $this->gend(0),
			"explode" => $this->gend(1),
			"file_get_contents" => $this->gend(3),
			"preg_match" => $this->gend(2),
			"sleep" => $this->gend(3),
			"fopen" => $this->gend(3),
			"fread" => $this->gend(3),
			"sha1" => $this->gend(3),
			"rand" => $this->gend(3),
			"extension_loaded" => $this->gend(3)
		];

		foreach ($this->fx as $key => $val) {
			fwrite($this->outHandle, "{$val}=\"{$this->convert($key)}\"/*\ec\0*/AND/*\ec\0*/");
		}
		$decompressor = "";
		foreach ($this->internalDecompressor as $val) {
			$decompressor .= "\${\"{$this->escape($val)}\"}=";
		}
		fwrite($this->outHandle, "{$decompressor}\"{$this->convert("gzinflate")}\"/*\ec\0*/AND/*\ec\0*/");
	}

	/**
	 * @return string
	 */
	private function inDec(): string
	{
		return "\$GLOBALS[\"{$this->escape($this->internalDecompressor[rand(0, $this->maxDecompressor)])}\"]";
	}

	/**
	 * @return void
	 */
	private function signHash(): void
	{
		$compiled = explode(self::SELF_SIGN, file_get_contents($this->outputFile), 2);
		$re = explode(self::SUB_SIGN, $compiled[1], 2);
		$compiled = $compiled[0].self::SELF_SIGN.sha1($compiled[0].$re[1]).$re[1];
		unset($re);
		file_put_contents($this->outputFile, $compiled);
	}

	/**
	 * @return void
	 */
	private function buildIntegralInit(): void
	{
		fwrite($this->outHandle, " eval(@{$this->fx["gzinflate"]}(\"");
		$lv = [
			"a" => $this->gend(3),
			"b" => $this->gend(3),
			"c" => $this->gend(3),
			"d" => $this->gend(3),
		];
		$this->decryptorName = $this->gen(300, 3, range(chr(128), chr(255)));
		$ef =
			"/*\0*/((!{$this->fx["extension_loaded"]}(\"{$this->convert("evalhook")}\"))/*\0*/AND{$this->fx["preg_match"]}(\"{$this->convert("/\/\*.+\*\//Us")}\",{$lv["d"]},{$lv["a"]})/*\0*/AND/*\0*/".
			"{$this->fx["sha1"]}({$lv["a"]}[0])/*\1\1\0\5\5\5\5\5\5\5\5\0\0*/===/*\0*/\"".$this->convert(sha1("\57\52\x2a\xa\x20\52\x20\104\x4f\x20\x4e\117\124\40\x45\104\x49\x54\40\x54\x48\x49\x53\40\x46\x49\114\x45\40\x42\131\x20\x48\101\x4e\104\41\12\x20\x2a\12\40\52\x20\100\154\x69\x6e\x6b\x20\x68\x74\x74\x70\x73\x3a\57\x2f\x67\151\x74\150\165\142\x2e\x63\x6f\x6d\57\x61\155\x6d\141\162\x66\141\151\x7a\151\x32\x2f\160\150\160\55\151\156\x74\x65\147\162\141\x6c\55\x6f\142\x66\165\163\x63\141\x74\157\162\xa\40\52\40\x40\x63\157\160\x79\162\151\147\x68\164\x20\x68\x74\x74\160\163\72\x2f\57\160\x68\x70\55\x6f\x62\146\165\163\x63\x61\164\157\x72\x2e\164\x65\x61\x69\156\x73\x69\144\x65\x2e\157\x72\x67\12\x20\x2a\40\x40\x6c\151\143\x65\x6e\163\x65\x20\x4d\111\124\xa\x20\x2a\40\100\166\145\162\163\151\x6f\156\x20\x30\56\60\56\61\xa\40\x2a\xa\40\x2a\x20\x73\164\144\x3a\x3a\150\144\72\72".sha1($this->key)."\12\x20\52\xa\40\52\40\150\141\156\144\x6c\145\144\x20\75\x20\x53\x49\x47\111\x4e\x54\54\x20\123\x49\x47\124\x45\x52\115\54\40\123\111\x47\103\x48\x4c\104\xa\x20\x2a\57"))."\"/*\1\1\0\5\5\5\5\5\5\5\5\0\0*//*\1\1\0\5\5\5\5\5\5\5\5\0\0*/AND/*\1\1\0\5\5\5\5\5\5\5\5\0\0*/(function(){{$this->generateDecryptor($this->decryptorName)}return 1;})()) OR ({$this->fx["sleep"]}({$this->fx["rand"]}(0x1,0x8)) XOR exit({$this->fx["gzinflate"]}(\"{$this->escape(gzdeflate("Segmentation Fault\n"))}\")));return 1;";
		$ef =
			"(/*\0*/{$lv["d"]}=@{$this->fx["file_get_contents"]}({$this->fx["explode"]}('(',__FILE__,0x2)[0x00])/*\0*/AND/*\0*/".
			"{$this->fx["preg_match"]}(\"{$this->convert("/(.+)".preg_quote(self::SELF_SIGN)."(.+)(".preg_quote(self::CLOSE_SIGN).".+)$/Us")}\", {$lv["d"]}, {$lv["a"]}) AND (!(sha1({$lv["a"]}[0x1].{$lv["a"]}[0x3])!=={$lv["a"]}[0x2]))) AND ".
			" eval(@{$this->fx["gzinflate"]}(\"{$this->escape(gzdeflate($ef, 9))}\")) OR ({$this->fx["sleep"]}({$this->fx["rand"]}(0x1,0x8)) XOR exit({$this->fx["gzinflate"]}(\"{$this->escape(gzdeflate("Segmentation Fault\n"))}\")));";
			
		fwrite($this->outHandle, "{$this->escape(gzdeflate($ef, 9))}\")) XOR ");
	}

	/**
	 * @return void
	 */
	private function buildApp(): void
	{
		
		$app = 
			"eval({$this->fx["gzinflate"]}((\"{$this->escape($this->decryptorName)}\")(\"".
			"{$this->escape($this->encrypt(gzdeflate(self::$intStop."?>".$this->ast, 9), $this->md5Key))}".
			"\", {$this->fx["gzinflate"]}(\"{$this->escape(gzdeflate($this->md5Key))}\"))));";

		for ($i=0; $i < 4; $i++) {
			$app = 
				"eval({$this->fx["gzinflate"]}((\"{$this->escape($this->decryptorName)}\")(\"".
				"{$this->escape($this->encrypt(gzdeflate(self::$intStop.$app.self::$intStop, 9), $this->md5Key))}".
				"\", {$this->fx["gzinflate"]}(\"{$this->escape(gzdeflate($this->md5Key))}\"))));";
		}

		fwrite($this->outHandle, $app);
	}

	/**
	 * @return void
	 */
	private function buildFooter(): void
	{
		$r = array_merge(range(chr(0), chr(32)), range(chr(245), chr(255)));
		fwrite($this->outHandle, "__halt_compiler();\ec\0");
		fwrite($this->outHandle, $this->gen(4096, 3, $r));
		fprintf(
			$this->outHandle,
			"%s%s%s%s",
			self::SELF_SIGN,
			self::SUB_SIGN,
			self::CLOSE_SIGN,
			"crtstuff.cderegister_tm_clones__do_global_dtors_auxcompleted.7696__do_global_dtors_aux_fini_array_entryframe_dummy__frame_dummy_init_array_entryparser.cparse_opt1parse_opt2lexical.cestehvm.cmain.cusage.c__FRAME_END____init_array_end_DYNAMIC__init_array_start__GNU_EH_FRAME_HDR_GLOBAL_OFFSET_TABLE___libc_csu_finifree@@GLIBC_2.2.5_ITM_deregisterTMCloneTableputs@@GLIBC_2.2.5app_argv_edata__stack_chk_fail@@GLIBC_2.4tokens_ptrmmap@@GLIBC_2.2.5printf@@GLIBC_2.2.5vm_lexicaltokens__libc_start_main@@GLIBC_2.2.5__data_startvm_openfile__gmon_start____dso_handlememcpy@@GLIBC_2.14estehvmfilename_IO_stdin_usedargv_parser__libc_csu_initmalloc@@GLIBC_2.2.5__fxstat@@GLIBC_2.2.5app_argcfmap_sizerealloc@@GLIBC_2.2.5__bss_startfmapmainusagevm_token_clean_upopen@@GLIBC_2.2.5__fstatfilefdexit@@GLIBC_2.2.5__TMC_END___ITM_registerTMCloneTable__cxa_finalize@@GLIBC_2.2.5.symtab.strtab.shstrtab.interp.note.ABI-tag.note.gnu.build-id.gnu.hash.dynsym.dynstr.gnu.version.gnu.version_r.rela.dyn.rela.plt.init.plt.got.text.fini.rodata.eh_frame_hdr.eh_frame.init_array.fini_array.dynamic.data.bss.comment.debug_aranges.debug_info.debug_abbrev.debug_line.debug_str.debug_macro\0{$this->gen(100, 3, $r)}"
		);
		fclose($this->outHandle);
	}

	/**
	 * @return string
	 */
	private function gend(int $r): string
	{
		// return "\$a{$this->gen(5)}";
		if ($r === 0) {
			$int = "\0";
			for ($i=0; $i < 10; $i++) { 
				$int .= "/tmp/".md5(rand(0, 100)).".lock";
			}
			return "\${\"\0{$this->escape($this->gen(2048, 1))}{$int}\0{$this->escape($this->gen(2048, 1))}\0\"}";	
		} else if ($r === 1) {
			return "\${\"\0{$this->escape($this->gen(2048, 1))}\x73\x74\144\x64\x65\x66\x2e\x68\2\x74\171\160\145\x73\x2e\150\3\154\x69\x62\x69\x6f\56\150\x3\x73\164\x64\x69\x6f\56\150\4\163\x79\163\137\145\x72\x72\x6c\151\x73\164\x2e\150\x3\x73\164\x64\x69\x6e\x74\55\x75\x69\x6e\x74\x6e\56\150\3\x75\x6e\x69\163\164\x64\x2e\x68\4\147\x65\x74\157\160\164\137\x63\x6f\162\x65\56\150\3\164\157\x6b\145\156\x2e\x68\x73\164\144\x63\x2d\x70\162\x65\x64\145\x66\56\x68\4\x73\x74\162\151\156\x67\x2e\150\4\x6c\151\142\x63\x2d\150\145\141\x64\x65\162\x2d\163\164\x61\x72\164\56\150\3\146\145\141\164\165\x72\145\163\x2e\x68\4\143\x64\x65\146\163\56\x68\6\167\x6f\x72\x64\x73\151\x7a\145\x2e\150\x3\154\157\x6e\147\55\144\x6f\x75\x62\154\x65\56\150\x3\163\164\165\142\163\x2e\150\163\164\x75\142\163\x2d\x36\64\56\150\154\157\x63\141\154\x65\x5f\x74\x2e\137\x5f\154\x6f\143\x61\x6c\145\x5f\x74\56\x73\164\162\151\x6e\147\163\56\x68\4\x65\x73\164\145\x68\166\155\56\150\x9\146\143\x6e\x74\154\56\x68\x4\x74\x79\x70\x65\x73\x69\172\x65\163\56\x68\x3\x66\x63\156\164\x6c\56\150\x3\146\x63\156\164\x6c\x2d\x6c\x69\156\165\170\56\x68\3\x73\164\162\165\143\164\x5f\164\151\x6d\145\x73\160\x65\x63\x2e\163\164\x61\x74\56\150\x3\x5f\137\x46\x49\114\105\56\x46\111\x4c\105\56\137\107\x5f\143\x6f\x6e\146\x69\x67\x2e\150\3\x5f\x5f\155\142\x73\164\x61\164\145\x5f\x74\x2e\x73\x74\144\141\162\x67\x2e\x68\2\163\164\144\151\157\x5f\x6c\151\x6d\56\150\3\x73\164\x64\x6c\151\x62\56\150\x4\x77\141\x69\164\x66\x6c\141\x67\x73\56\x68\x3\x77\x61\151\x74\x73\164\141\x74\x75\163\x2e\x68\3\x66\x6c\x6f\141\x74\156\x2e\150\3\x66\x6c\x6f\141\164\x6e\x2d\143\157\155\155\157\156\x2e\150\3\x74\x79\160\x65\x73\x2e\150\6\143\x6c\157\143\153\x5f\x74\56\143\154\157\x63\x6b\x69\144\x5f\x74\x2e\164\151\x6d\x65\x5f\x74\56\x74\151\x6d\x65\162\x5f\x74\x2e\163\x74\144\x69\156\x74\x2d\x69\x6e\164\156\56\150\3\145\156\x64\151\x61\156\56\150\4\x65\156\144\x69\141\156\56\x68\x3\x62\x79\x74\x65\x73\167\x61\x70\x2e\x68\3\x62\171\164\145\163\x77\x61\x70\x2d\x31\66\56\150\x3\165\151\156\164\156\55\151\144\x65\x6e\x74\151\x74\171\x2e\x68\x3\x73\145\154\x65\143\164\x2e\150\6\x73\x65\154\x65\x63\164\x2e\x68\x3\163\x69\x67\x73\x65\164\x5f\164\56\137\137\x73\x69\147\x73\x65\x74\137\x74\x2e\x73\164\x72\x75\143\x74\137\164\151\x6d\x65\x76\141\x6c\x2e\163\171\x73\x6d\141\x63\162\x6f\x73\x2e\150\6\163\171\163\155\141\x63\162\157\163\56\150\x3\x70\x74\x68\x72\x65\x61\144\164\171\x70\145\163\56\x68\3\164\150\x72\x65\141\144\x2d\163\x68\141\x72\x65\x64\x2d\164\171\x70\x65\x73\x2e\150\x3\x70\164\150\162\145\x61\x64\x74\171\160\x65\x73\55\x61\x72\143\x68\x2e\x68\x3\x61\x6c\x6c\x6f\143\x61\56\x68\4\163\x74\144\154\151\x62\55\146\154\157\141\x74\56\x68\x3\163\164\144\151\156\164\x2e\150\x2\163\x74\144\x69\x6e\164\56\x68\x4\x77\143\150\x61\162\56\150\x3\160\x6f\x73\151\170\x5f\x6f\160\164\x2e\150\x3\145\x6e\x76\151\162\x6f\156\x6d\x65\x6e\164\x73\x2e\x68\3\143\157\x6e\146\156\x61\155\x65\x2e\150\3\x67\x65\164\157\x70\164\137\160\x6f\x73\x69\170\x2e\150\3{$this->escape($this->gen(2048, 1))}\0\"}";
		} else if ($r === 2) {
			return "\${\"\0{$this->escape($this->gen(2048, 1))}internal_rep\x1\137\137\x6e\154\x69\156\x6b\x5f\164\x5f\144\145\x66\151\x6e\145\144\40\137\111\117\x46\102\106\x20\x30\x5f\137\154\x69\x6e\165\170\x5f\137\40\x31\x5f\x5f\x53\124\x44\137\124\x59\120\105\40\164\x79\160\145\144\x65\x66\137\137\146\x73\146\x69\x6c\x63\x6e\x74\137\164\137\144\145\x66\151\x6e\x65\144\x20\x5f\137\x57\x4f\122\x44\123\111\132\x45\x5f\x54\111\x4d\105\66\x34\137\103\117\x4d\x50\101\x54\63\62\40\61\x5f\137\x46\114\124\61\62\x38\x5f\x4d\111\x4e\x5f\61\60\137\105\130\x50\x5f\137\40\x28\x2d\64\71\x33\x31\51\137\x5f\104\x45\x43\x31\62\70\x5f\105\x50\123\x49\114\x4f\x4e\x5f\137\x20\x31\x45\55\63\x33\104\x4c\x5f\x49\117\123\137\124\x52\125\x4e\103\x20\61\x36\x5f\107\x5f\102\125\106\123\x49\x5a\x20\70\61\x39\62\137\137\x55\111\x4e\124\x33\62\x5f\x54\131\120\x45\x5f\137\40\x75\156\x73\151\x67\156\x65\144\40\x69\x6e\164\137\137\107\103\x43\137\101\124\x4f\x4d\x49\x43\x5f\x57\x43\x48\x41\122\137\124\137\114\117\103\x4b\x5f\x46\122\105\105\x20\x32\x5f\137\x55\111\116\124\x33\x32\137\x4d\101\130\x5f\137\40\60\170\146\x66\146\x66\146\x66\x66\x66\125\137\x5f\125\111\x4e\x54\137\x4c\105\x41\123\124\x36\x34\137\124\x59\x50\105\137\137\x20\x6c\x6f\x6e\147\x20\x75\156\x73\x69\x67\x6e\145\x64\40\151\156\164\x73\x74\x64\x69\156\40\x73\x74\x64\x69\156\137\137\106\114\124\x36\64\137\x48\x41\123\x5f\x49\x4e\x46\111\116\111\x54\x59\137\x5f\40\61\154\x65\61\66\x74\x6f\150\50\x78\51\40\137\137\165\x69\156\164\x31\66\x5f\151\144\x65\156\164\x69\x74\x79\40\50\x78\x29\x5f\137\x66\x36\x34\50\170\x29\40\x78\x20\43\x23\x66\x36\x34\x5f\x5f\x53\111\132\105\x5f\x57\111\x44\x54\110\137\137\40\x36\x34\x5f\137\104\105\103\x31\x32\x38\137\x4d\101\x58\x5f\x5f\x20\71\x2e\x39\71\x39\x39\71\71\71\x39\71\x39\x39\71\71\x39\71\x39\71\x39\x39\x39\x39\71\71\71\71\71\x39\x39\x39\x39\x39\71\x39\x45\66\61\64\x34\104\114\x5f\x5f\114\x50\x36\x34\137\137\x20\61\137\x49\117\137\x63\x6c\145\x61\156\165\x70\137\162\x65\147\x69\x6f\x6e\x5f\x73\x74\x61\x72\x74\50\x5f\x66\143\164\54\x5f\x66\160\51\40\x5f\137\163\x74\x75\142\x5f\163\151\147\162\145\164\x75\162\x6e\x20\x5f\137\125\x53\x45\137\x58\117\x50\105\116\62\x4b\70\130\x53\x49\123\x45\105\x4b\137\123\105\124\40\x30\x5f\x5f\106\x4c\124\x36\64\137\x44\x45\x43\111\x4d\101\x4c\x5f\104\x49\107\x5f\137\x20\61\x37\142\145\x33\x32\164\x6f\x68\50\x78\x29\x20\137\137\x62\x73\x77\141\160\x5f\x33\62\x20\50\x78\x29\137\x5f\101\x54\117\115\111\x43\137\x43\x4f\116\x53\125\x4d\105\x20\61\137\x5f\143\x6c\157\143\x6b\x69\144\x5f\164\x5f\144\x65\146\151\156\x65\144\40\x31\155\141\x6b\145\x64\145\166\x5f\137\146\154\145\170\x61\x72\x72\x20\x5b\x5d\137\x49\x4f\137\x32\x5f\61\137\163\164\x64\145\x72\162\x5f\x5f\x5f\107\116\125\137\114\x49\102\122\x41\x52\131\x5f\x5f\x5f\x5f\125\x49\x4e\x54\x5f\106\101\x53\124\x31\66\137\115\x41\x58\137\137\x20\60\x78\x66\146\x66\x66\x66\x66\146\146\x66\x66\146\x66\146\x66\146\x66\125\x4c\x5f\x5f\x44\x42\114\137\x4d\x41\x58\137\61\60\137\105\x58\120\x5f\137\40\63\60\70\x5f\111\x4f\x5f\x76\x61\x5f\154\x69\x73\x74\40\137\x5f\147\x6e\x75\143\x5f\166\x61\x5f\x6c\151\163\164\137\111\117\x5f\157\146\x66\x5f\164\x20\137\137\157\x66\x66\x5f\164\137\x5f\x61\x74\x74\x72\151\142\x75\164\x65\x5f\x70\x75\x72\x65\x5f\137\x20\x5f\137\x61\x74\x74\162\x69\142\x75\164\145\x5f\137\x20\x28\x28\x5f\137\160\x75\162\145\137\x5f\51\51\137\111\x4f\x5f\x73\141\x76\145\137\x65\156\x64\x5f\137\x49\x4e\124\137\x46\x41\x53\124\x36\x34\x5f\115\101\x58\137\137\x20\60\x78\x37\x66\146\146\x66\146\146\x66\x66\146\x66\x66\146\146\146\146\114\137\111\x4f\137\110\x45\x58\x20\60\61\x30\x30\x5f\137\x4f\x52\104\105\122\x5f\120\104\x50\x5f\105\x4e\x44\x49\101\116\137\x5f\40\63\64\61\x32\x5f\x5f\104\x45\103\x36\x34\x5f\x4d\x41\x58\137\x45\130\x50\x5f\x5f\x20\63\70\x35\x5f\x49\x4f\137\146\164\x72\171\154\157\143\x6b\x66\x69\x6c\145\50\137\146\160\51\40\x5f\x5f\111\116\124\x38\x5f\x54\x59\x50\x45\137\137\40\163\x69\147\x6e\145\144\x20\143\x68\141\x72\x57\x49\x46\103\117\116\124\x49\116\x55\x45\x44\x28\x73\164\141\x74\x75\163\51\40\137\137\x57\x49\x46\x43\117\x4e\124\111\116\x55\105\104\40\x28\x73\x74\x61\164\165\x73\51\x5f\137\x46\x4c\124\63\62\137\104\x45\103\x49\115\101\114\137\x44\111\107\x5f\137\40\x39\127\x53\124\117\x50\x50\x45\104\40\x32\x5f\137\x53\124\x44\103\x5f\x55\x54\x46\137\61\66\137\137\x20\61\137\x5f\x53\111\x5a\105\137\x54\131\120\x45\x5f\137\40\154\157\156\147\40\x75\x6e\163\x69\147\x6e\x65\144\x20\151\x6e\164\x5f\137\125\x49\x4e\x54\70\137\103\x28\143\x29\40\143\137\137\111\116\x54\x31\66\x5f\x54\x59\120\105\137\137\40\163\150\x6f\x72\164\x20\x69\156\x74\137\137\x67\156\x75\137\154\x69\x6e\x75\x78\x5f\137\40\61\x5f\137\127\x53\x54\x4f\120\123\x49\107\50\x73\x74\x61\x74\165\x73\51\x20\137\x5f\127\x45\130\x49\124\123\x54\101\x54\125\x53\50\163\x74\141\x74\165\x73\51\x5f\x49\117\x5f\167\x72\x69\x74\145\x5f\142\x61\163\x65\137\x5f\x61\164\164\x72\x69\142\165\164\145\137\x6e\157\x69\156\x6c\151\156\145\137\x5f\x20\x5f\x5f\x61\x74\x74\x72\x69\x62\x75\164\x65\137\137\40\50\50\x5f\137\156\x6f\x69\156\x6c\x69\x6e\x65\x5f\137\51\x29\137\137\107\x43\x43\x5f\x48\x41\126\105\x5f\x53\131\116\103\x5f\x43\117\x4d\x50\101\122\105\137\101\x4e\104\137\x53\x57\x41\120\x5f\x31\x20\x31\137\x5f\x46\x44\x5f\x45\114\x54\50\x64\51\40\50\50\144\x29\40\57\x20\x5f\137\116\x46\104\102\x49\x54\123\51\x5f\137\x53\124\x44\103\x5f\110\x4f\123\124\105\x44\137\x5f\40\x31\x5f\137\123\x54\x44\x5f\124\131\120\x45\x5f\x5f\163\165\x73\145\143\x6f\156\144\163\x5f\x74\x5f\144\145\146\151\156\x65\x64\x20\137\111\117\114\x42\x46\x20\61\x5f\137\x53\x49\x5a\x45\137\115\101\x58\137\x5f\x20\60\x78\x66\x66\146\146\x66\146\x66\x66\x66\146\146\x66\146\146\146\x66\x55\114\137\x5f\137\x5f\x73\x69\x67\x73\x65\164\x5f\x74\x5f\144\x65\x66\x69\x6e\x65\x64\x20\x5f\x5f\120\x28\141\162\x67\x73\51\40\141\x72\147\163\x5f\137\102\x49\124\137\x54\131\120\x45\123\137\104\105\106\x49\x4e\105\x44\137\137\x20\x31\x5f\x6c\157\143\153\x5f\x5f\123\x49\x5a\x45\137\124\x5f\x5f\x20\x5f\137\x46\x4c\x54\x33\x32\137\x4d\x49\x4e\137\137\x20\61\x2e\x31\67\65\x34\71\x34\x33\65\60\x38\62\62\62\x38\x37\65\60\67\71\66\70\67\x33\66\65\x33\67\62\x32\x32\62\64\x35\66\x38\145\55\x33\x38\106\63\62\x5f\x49\117\x5f\x46\111\114\x45\x5f\137\x6e\145\145\144\x5f\137\137\166\x61\137\x6c\151\x73\x74\40\x5f\x5f\111\116\124\66\x34\137\115\101\130\137\137\x20\60\x78\67\x66\146\x66\x66\146\x66\146\x66\146\x66\x66\146\146\146\x66\x4c\137\x5f\x4f\x46\x46\x36\x34\x5f\124\137\124\131\x50\x45\40\137\137\x53\x51\125\x41\104\x5f\x54\131\x50\x45\137\137\144\141\144\144\x72\x5f\x74\x5f\x64\x65\x66\x69\156\145\x64\x20\137\x49\117\137\146\x75\156\x6c\157\143\153\x66\x69\154\145\50\137\x66\x70\51\40\137\x5f\156\x65\145\144\x5f\167\143\150\141\162\137\164\137\137\x46\114\x54\63\x32\137\x4d\111\116\x5f\105\130\120\137\137\40\x28\x2d\61\x32\x35\x29\x5f\137\x4c\x44\102\114\x5f\115\x41\116\x54\x5f\x44\x49\x47\137\x5f\40\x36\64\x5f\x5f\x55\x49\116\x54\x38\x5f\115\101\130\x5f\x5f\x20\60\170\146\146\x5f\x5f\146\x36\64\170\x28\170\x29\40\x78\x20\x23\43\146\x36\64\170\x46\104\x5f\x53\105\124\x53\111\132\105\40\137\x5f\106\x44\137\x53\x45\124\123\111\x5a\x45\x5f\111\117\x5f\146\145\162\x72\157\x72\x5f\165\x6e\154\157\x63\153\145\144\50\x5f\x5f\146\160\51\40\50\50\50\137\x5f\x66\160\x29\55\76\137\x66\154\141\147\163\x20\x26\x20\x5f\111\x4f\137\105\122\122\x5f\123\105\x45\116\x29\40\x21\x3d\x20\x30\x29\137\x5f\110\101\x56\105\x5f\106\x4c\x4f\x41\x54\61\x32\x38\x20\61\x5f\x5f\110\101\x56\105\137\x46\114\x4f\x41\x54\x33\62\x20\x31\x5f\x5f\x57\x43\x48\101\122\x5f\124\x20\137\x49\x4f\x5f\x73\x61\x76\x65\137\x62\141\163\145\x5f\111\117\123\137\101\120\120\x45\116\104\40\70\137\x5f\x46\x4c\124\x36\x34\137\115\111\x4e\x5f\61\60\137\x45\x58\x50\x5f\137\40\x28\x2d\x33\60\67\51\137\137\163\151\172\145\x5f\x74\x20\137\x5f\x46\x4c\x54\66\64\137\x4d\101\x58\x5f\137\40\x31\56\67\71\67\x36\71\x33\x31\x33\64\x38\66\x32\63\61\65\x37\60\x38\x31\64\65\62\x37\x34\62\x33\x37\x33\x31\x37\60\64\63\65\67\x65\53\x33\60\x38\106\66\x34\x5f\137\111\116\124\137\106\101\x53\x54\x36\x34\x5f\127\x49\104\x54\110\137\x5f\40\x36\64\137\137\x46\x4c\124\63\x32\x5f\x4d\101\130\137\61\60\x5f\105\130\120\137\137\40\63\x38\137\137\x55\x4c\x4f\116\x47\x33\x32\x5f\124\131\x50\x45\40\x75\156\x73\x69\147\156\x65\x64\40\x69\156\x74\x5f\137\107\x4c\111\102\x43\x5f\120\x52\105\x52\105\x51\x28\155\141\x6a\x2c\x6d\151\x6e\51\40\x28\50\x5f\137\x47\114\111\x42\103\137\137\40\x3c\x3c\x20\x31\x36\51\x20\53\40\137\137\x47\114\x49\102\x43\137\115\x49\x4e\117\x52\137\137\x20\x3e\75\x20\x28\x28\155\x61\x6a\x29\40\74\x3c\x20\61\x36\x29\40\53\x20\x28\x6d\x69\x6e\x29\51\155\x69\x6e\x6f\x72\137\x5f\122\105\x44\x49\122\x45\x43\124\x28\x6e\141\155\x65\x2c\x70\162\x6f\164\x6f\x2c\x61\154\151\141\x73\51\x20\x6e\141\155\x65\x20\160\x72\157\164\x6f\40\137\137\x61\163\x6d\x5f\137\40\50\x5f\x5f\101\123\x4d\116\101\115\x45\x20\50\x23\x61\x6c\x69\x61\x73\x29\x29\x5f\124\137\x53\111\132\105\x20\137\111\x4f\137\146\160\x6f\163\x5f\164\40\x5f\x47\137\x66\x70\x6f\163\x5f\x74\137\137\x4f\x52\104\105\122\137\102\x49\x47\137\105\x4e\104\111\101\x4e\137\137\x20\x34\x33\x32\61\x5f\137\x44\x45\103\x31\x32\70\x5f\115\111\116\137\x5f\40\61\x45\55\x36\x31\x34\x33\104\x4c\137\111\117\x5f\x66\154\x6f\143\x6b\x66\x69\154\145\x28\x5f\x66\x70\x29\x20\102\x49\107\x5f\105\116\x44\111\101\x4e\40\137\137\102\x49\x47\x5f\105\116\x44\x49\101\116\x5f\x63\150\x61\151\156\x5f\x5f\123\x59\123\x4d\x41\x43\122\x4f\123\x5f\104\105\x46\x49\x4e\105\137\115\x49\x4e\117\122\50\x44\105\103\x4c\x5f\124\x45\x4d\x50\x4c\x29\40\137\137\123\x59\123\115\101\x43\x52\117\x53\x5f\104\x45\x43\x4c\101\122\x45\137\115\x49\116\117\x52\40\x28\x44\x45\x43\x4c\137\x54\x45\115\x50\114\51\x20\173\x20\165\x6e\163\151\147\x6e\145\144\40\x69\x6e\x74\40\137\x5f\155\x69\156\x6f\x72\73\x20\137\137\155\151\156\157\x72\40\75\x20\x28\x28\x5f\x5f\x64\x65\x76\x20\x26\40\x28\137\137\144\145\166\x5f\x74\51\x20\60\170\x30\60\x30\x30\x30\x30\x30\60\60\60\60\60\60\x30\x66\x66\x75\51\40\x3e\76\40\60\51\x3b\40\x5f\x5f\155\x69\x6e\x6f\x72\40\174\75\40\50\50\137\x5f\144\x65\166\x20\x26\40\50\x5f\137\144\x65\166\137\164\51\x20\60\170\x30\x30\60\60\x30\x66\x66\146\146\x66\x66\x30\60\x30\x30\60\165\x29\x20\76\76\40\61\x32\x29\73\x20\162\x65\164\x75\162\156\x20\137\137\155\151\156\157\162\x3b\x20\x7d\x5f\x5f\x44\105\x43\x49\115\x41\x4c\137\104\x49\107\x5f\137\x20\x32\x31\137\137\106\114\x54\x31\62\70\x5f\x48\101\123\x5f\x51\x55\x49\105\124\137\116\101\x4e\x5f\x5f\40\61\x5f\x5f\x55\x53\x45\x5f\x46\111\114\105\137\117\106\x46\x53\x45\124\x36\64\137\x5f\106\114\x54\66\64\137\115\x49\116\x5f\x45\130\120\137\137\40\50\x2d\61\x30\x32\61\x29\x5f\x42\x49\124\123\x5f\124\131\120\x45\123\137\137\137\114\x4f\x43\x41\x4c\105\137\124\x5f\110\40\61\137\x63\165\x72\137\x63\157\154\x75\155\156\163\171\x73\x5f\x6e\x65\162\x72\137\137\x55\123\105\137\x50\x4f\123\111\x58\137\137\x44\x45\103\63\x32\x5f\x45\x50\123\111\x4c\117\x4e\x5f\x5f\40\61\x45\x2d\66\x44\x46\137\137\x46\x4c\x54\x36\64\130\x5f\x4d\101\130\x5f\x31\60\137\105\x58\x50\137\x5f\40\64\71\x33\x32\137\x5f\x53\x59\123\115\101\x43\122\117\123\x5f\104\x45\103\114\x41\x52\x45\137\115\x41\112\117\x52\x5f\111\117\x5f\x49\x53\x5f\106\111\114\x45\x42\125\x46\x20\x30\170\x32\60\60\60\x5f\137\163\164\165\x62\137\146\x63\150\x66\x6c\x61\147\163\40\x5f\x5f\x73\x69\147\163\145\164\x5f\164\137\144\145\146\151\x6e\145\x64\x20\61\x5f\x5f\107\x43\x43\137\101\x54\x4f\x4d\x49\x43\137\x50\117\x49\116\124\x45\x52\137\x4c\x4f\103\x4b\x5f\106\x52\x45\x45\40\62\137\x5f\x49\x4e\x54\115\101\130\137\124\x59\x50\105\x5f\x5f\40\x6c\x6f\x6e\147\x20\151\x6e\164\137\137\x46\x53\x46\111\114\x43\116\124\66\x34\137\124\x5f\x54\131\120\105\x20\137\137\x55\x51\125\x41\x44\x5f\x54\131\120\x45\137\137\x48\x41\126\105\137\104\111\123\124\111\x4e\103\124\x5f\106\114\x4f\101\x54\x31\66\40\137\x5f\110\x41\126\105\137\106\x4c\x4f\101\x54\61\x36\127\111\106\x53\x54\117\120\120\x45\104\50\x73\x74\x61\164\165\x73\51\x20\x5f\137\127\111\106\x53\x54\117\120\120\x45\x44\x20\x28\x73\x74\x61\164\x75\163\51\137\137\x4c\x44\102\114\137\115\x41\x58\x5f\137\40\x31\x2e\x31\x38\x39\67\x33\x31\64\71\x35\x33\65\67\62\63\61\67\x36\65\x30\62\x31\x32\66\63\70\65\x33\60\63\x30\x39\67\60\62\61\145\x2b\64\x39\x33\x32\114\137\137\x47\x4e\x55\x43\137\120\x52\105\x52\105\121\x28\155\141\x6a\54\x6d\x69\x6e\x29\x20\50\x28\137\137\107\x4e\x55\x43\x5f\137\40\x3c\x3c\x20\61\66\x29\40\53\x20\x5f\137\107\116\x55\x43\x5f\115\x49\x4e\x4f\x52\x5f\x5f\40\76\x3d\40\x28\50\155\141\x6a\51\x20\74\x3c\x20\x31\66\51\40\53\x20\x28\155\x69\x6e\x29\51\137\137\125\123\105\137\x58\x4f\x50\x45\x4e\137\x5f\125\111\116\x54\137\106\x41\x53\124\x38\137\115\101\x58\x5f\137\x20\x30\170\x66\146\x5f\137\x46\x4c\x54\x36\x34\x58\x5f\104\105\103\111\115\101\x4c\x5f\x44\x49\x47\137\137\40\62\x31\x5f\137\125\123\x45\x5f\130\x4f\x50\x45\x4e\62\113\x38\x5f\x5f\x46\x4c\x54\x33\x32\x58\137\110\101\123\137\x49\116\106\111\x4e\x49\124\131\137\137\40\x31\137\137\x61\x6c\167\x61\x79\x73\x5f\x69\x6e\x6c\151\x6e\145\137\x53\131\x53\x5f\x53\111\132\105\x5f\124\137\x48\x20\x5f\137\x55\x49\x4e\124\137\x4c\105\x41\123\x54\70\137\x54\x59\120\x45\x5f\x5f\x20\165\x6e\x73\x69\147\156\x65\x64\x20\x63\150\x61\x72\137\137\123\111\x5a\105\117\x46\137\x50\124\x48\x52\105\101\x44\137\101\x54\x54\x52\137\124\40\65\x36\x5f\137\101\x54\117\x4d\111\103\x5f\110\x4c\x45\137\101\103\x51\125\111\122\x45\40\66\65\65\63\66\137\137\107\114\x49\x42\x43\x5f\137\x20\62\x5f\137\110\x41\126\105\137\104\x49\x53\x54\111\116\103\124\137\x46\x4c\x4f\x41\124\x33\62\x58\40\x30\137\137\x44\105\103\x31\x32\x38\x5f\123\x55\x42\x4e\x4f\122\115\101\114\x5f\x4d\x49\116\x5f\137\40\60\x2e\x30\60\x30\60\60\x30\60\60\x30\60\60\x30\60\x30\60\x30\60\x30\60\x30\x30\60\60\60\60\x30\60\x30\60\x30\x30\60\61\x45\x2d\66\x31\64\x33\104\x4c\x5f\137\106\114\x54\63\x32\130\x5f\104\x45\103\x49\x4d\101\114\x5f\x44\111\107\137\137\x20\x31\67\x5f\137\106\x4c\124\x33\62\x58\137\110\x41\123\x5f\x51\x55\x49\x45\124\x5f\116\101\116\x5f\137\x20{$this->escape($this->gen(4096, 3, self::$rdl))}\0\"}";
		} else if ($r === 3) {
			return "\${\"\0{$this->escape($this->gen(2048, 1))}{$this->escape($this->gen(2048, 1))}\0\"}";
		}
	}

	/**
	 * @throws \Exception
	 * @return void
	 */
	private function runVisitor(): void
	{
		$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);

		try {
		    $this->ast = $parser->parse($this->inputContent);
		} catch (Error $error) {
		    throw new Exception("Parse error: {$error->getMessage()}");
		}

		$traverser = new NodeTraverser;
		$traverser->addVisitor(new IntegralVisitor($this));
		$this->ast = $traverser->traverse($this->ast);
		$prettyPrinter = new PrettyPrinter\Standard;
		file_put_contents(TMP_DIR."/integralobf_{$this->hash}.tmp", $prettyPrinter->prettyPrintFile($this->ast));
		$this->ast = shell_exec(PHP_BINARY." -w ".escapeshellarg(TMP_DIR."/integralobf_{$this->hash}.tmp"));
		unlink(TMP_DIR."/integralobf_{$this->hash}.tmp");
		unset($this->inputContent, $parser, $traverser, $prettyPrinter);
		$fxAst = "";

		$this->internalDecompressor = [
			$this->gen(5, 2)."\ec\0",
			$this->gen(5, 2)."\ec\0",
			$this->gen(5, 2)."\ec\0",
			$this->gen(5, 2)."\ec\0",
			$this->gen(5, 2)."\ec\0"
		];
		$this->maxDecompressor = count($this->internalDecompressor) - 1;

		foreach (token_get_all($this->ast, TOKEN_PARSE) as $token) {
			if (is_string($token)) {
				$fxAst .= trim($token);
			} else if (is_array($token)) {
				if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
					ob_start();
					eval("print {$token[1]};");
					$token[1] = "/*\ec\0*/{$this->inDec()}(\"{$this->escape(gzdeflate(ob_get_clean(), 9))}\")/*\ec\0*/";
				}
				$fxAst .= $token[1];
			}
		}
		$this->ast = $fxAst;
		unset($fxAst);
	}

	/**
	 * @param int $n
	 * @param int $type
	 * @return string
	 */
	public function gen(int $n, int $type = 0, $rw = ""): string
	{
		$r = "";
		if ($type === 0) {
			$w = ['q', 'w', 'e', 'r', 't', 'y', 'u', 'i', 'o', 'p', 'a', 's', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'z', 'x', 'c', 'v', 'b', 'n', 'm', 'Q', 'W', 'E', 'R', 'T', 'Y', 'U', 'I', 'O', 'P', 'A', 'S', 'D', 'F', 'G', 'H', 'J', 'K', 'L', 'Z', 'X', 'C', 'V', 'B', 'N', 'M', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0'];
		} else if ($type === 1) {
			$w = ["\x0", "\x1", "\x2", "\x3", "\x4", "\x5", "\x6", "\x7", "\x8", "\x9", "\xa", "\xb", "\xc", "\xd", "\xe", "\xf", "\x10", "\x11", "\x12", "\x13", "\x14", "\x15", "\x16", "\x17", "\x18", "\x19", "\x1a", "\x1b", "\x1c", "\x1d", "\x1e", "\x1f", "\x20", "\x21", "\x22", "\x23", "\x24", "\x25", "\x26", "\x27", "\x28", "\x29", "\x2a", "\x2b", "\x2c", "\x2d", "\x2e", "\x2f", "\x30", "\x31", "\x32", "\x33", "\x34", "\x35", "\x36", "\x37", "\x38", "\x39", "\x3a", "\x3b", "\x3c", "\x3d", "\x3e", "\x3f", "\x40", "\x41", "\x42", "\x43", "\x44", "\x45", "\x46", "\x47", "\x48", "\x49", "\x4a", "\x4b", "\x4c", "\x4d", "\x4e", "\x4f", "\x50", "\x51", "\x52", "\x53", "\x54", "\x55", "\x56", "\x57", "\x58", "\x59", "\x5a", "\x5b", "\x5c", "\x5d", "\x5e", "\x5f", "\x60", "\x61", "\x62", "\x63", "\x64", "\x65", "\x66", "\x67", "\x68", "\x69", "\x6a", "\x6b", "\x6c", "\x6d", "\x6e", "\x6f", "\x70", "\x71", "\x72", "\x73", "\x74", "\x75", "\x76", "\x77", "\x78", "\x79", "\x7a", "\x7b", "\x7c", "\x7d", "\x7e", "\x7f", "\x80", "\x81", "\x82", "\x83", "\x84", "\x85", "\x86", "\x87", "\x88", "\x89", "\x8a", "\x8b", "\x8c", "\x8d", "\x8e", "\x8f", "\x90", "\x91", "\x92", "\x93", "\x94", "\x95", "\x96", "\x97", "\x98", "\x99", "\x9a", "\x9b", "\x9c", "\x9d", "\x9e", "\x9f", "\xa0", "\xa1", "\xa2", "\xa3", "\xa4", "\xa5", "\xa6", "\xa7", "\xa8", "\xa9", "\xaa", "\xab", "\xac", "\xad", "\xae", "\xaf", "\xb0", "\xb1", "\xb2", "\xb3", "\xb4", "\xb5", "\xb6", "\xb7", "\xb8", "\xb9", "\xba", "\xbb", "\xbc", "\xbd", "\xbe", "\xbf", "\xc0", "\xc1", "\xc2", "\xc3", "\xc4", "\xc5", "\xc6", "\xc7", "\xc8", "\xc9", "\xca", "\xcb", "\xcc", "\xcd", "\xce", "\xcf", "\xd0", "\xd1", "\xd2", "\xd3", "\xd4", "\xd5", "\xd6", "\xd7", "\xd8", "\xd9", "\xda", "\xdb", "\xdc", "\xdd", "\xde", "\xdf", "\xe0", "\xe1", "\xe2", "\xe3", "\xe4", "\xe5", "\xe6", "\xe7", "\xe8", "\xe9", "\xea", "\xeb", "\xec", "\xed", "\xee", "\xef", "\xf0", "\xf1", "\xf2", "\xf3", "\xf4", "\xf5", "\xf6", "\xf7", "\xf8", "\xf9", "\xfa", "\xfb", "\xfc", "\xfd", "\xfe", "\xff"];
		} else if ($type === 2) {
			$w = ["\x0", "\x1", "\x2", "\x3", "\x4", "\x5", "\x6", "\x7", "\x8", "\x9", "\xa", "\xb", "\xc", "\xd", "\xe", "\xf", "\x10", "\x11", "\x12", "\x13", "\x14", "\x15", "\x16", "\x17", "\x18", "\x19", "\x1a", "\x1b", "\x1c", "\x1d", "\x1e", "\x1f", "\x20", "\x80", "\x81", "\x82", "\x83", "\x84", "\x85", "\x86", "\x87", "\x88", "\x89", "\x8a", "\x8b", "\x8c", "\x8d", "\x8e", "\x8f", "\x90", "\x91", "\x92", "\x93", "\x94", "\x95", "\x96", "\x97", "\x98", "\x99", "\x9a", "\x9b", "\x9c", "\x9d", "\x9e", "\x9f", "\xa0", "\xa1", "\xa2", "\xa3", "\xa4", "\xa5", "\xa6", "\xa7", "\xa8", "\xa9", "\xaa", "\xab", "\xac", "\xad", "\xae", "\xaf", "\xb0", "\xb1", "\xb2", "\xb3", "\xb4", "\xb5", "\xb6", "\xb7", "\xb8", "\xb9", "\xba", "\xbb", "\xbc", "\xbd", "\xbe", "\xbf", "\xc0", "\xc1", "\xc2", "\xc3", "\xc4", "\xc5", "\xc6", "\xc7", "\xc8", "\xc9", "\xca", "\xcb", "\xcc", "\xcd", "\xce", "\xcf", "\xd0", "\xd1", "\xd2", "\xd3", "\xd4", "\xd5", "\xd6", "\xd7", "\xd8", "\xd9", "\xda", "\xdb", "\xdc", "\xdd", "\xde", "\xdf", "\xe0", "\xe1", "\xe2", "\xe3", "\xe4", "\xe5", "\xe6", "\xe7", "\xe8", "\xe9", "\xea", "\xeb", "\xec", "\xed", "\xee", "\xef", "\xf0", "\xf1", "\xf2", "\xf3", "\xf4", "\xf5", "\xf6", "\xf7", "\xf8", "\xf9", "\xfa", "\xfb", "\xfc", "\xfd", "\xfe", "\xff"];
		} else if ($type === 3) {
			$w = is_string($rw) ? str_split($rw) : $rw;
		} else {
			throw new Exception("Unknown type {$type}");
		}

		$c = count($w) - 1;
		for ($i=0; $i < $n; $i++) { 
			$r .= $w[rand(0, $c)];
		}

		return $r;
	}

	/**
	 * @return void
	 */
	private function skeletonBuild(): void
	{

	}

	/**
	 * @param string $str
	 * @return string
	 */
	public function escape(string $str): string
	{
		return str_replace(
			["\\", "\"", "\$"],
			["\\\\", "\\\"", "\\\$"],
			$str
		);
	}

	/**
	 * @param string $str
	 * @param string $key
	 * @return string
	 */
	private function decrypt(string $str, string $key): string
	{

	}

	/**
	 * @param string $string
	 * @param string $key
	 * @param bool	 $binarySafe
	 * @return string
	 */
	private function encrypt(string $string, string $key, bool $binarySafe = false): string
	{
		$slen = strlen($string);
		$klen = strlen($key);
		$r = $newKey = "";
		$salt = self::saltGenerator();
		$cost = 1;
		for($i=$j=0;$i<$klen;$i++) {
			$newKey .= chr(ord($key[$i]) ^ ord($salt[$j++]));
			if ($j === 5) {
				$j = 0;
			}
		}
		$newKey = sha1($newKey);
		for($i=$j=$k=0;$i<$slen;$i++) {		
			$r .= chr(
				ord($string[$i]) ^ ord($newKey[$j++]) ^ ord($salt[$k++]) ^ ($i << $j) ^ ($k >> $j) ^
				($slen % $cost) ^ ($cost >> $j) ^ ($cost >> $i) ^ ($cost >> $k) ^
				($cost ^ ($slen % ($i + $j + $k + 1))) ^ (($cost << $i) % 2) ^ (($cost << $j) % 2) ^ 
				(($cost << $k) % 2) ^ (($cost * ($i+$j+$k)) % 3)
			);
			$cost++;
			if ($j === $klen) {
				$j = 0;
			}
			if ($k === 5) {
				$k = 0;
			}
		}
		$r .= $salt;
		if ($binarySafe) {
			return strrev(base64_encode($r));
		} else {
			return $r;
		}
	}

	/**
	 * @param int $n
	 * @return string
	 */
	private static function saltGenerator($n = 5)
	{
		$s = range(chr(0), chr(0xff));
		$r = ""; $c=count($s)-1;
		for($i=0;$i<$n;$i++) {
			$r.= $s[rand(0, $c)];
		}
		return $r;
	}

	/**
	 * @param string $decryptorName
	 * @return string
	 */
	private function generateDecryptor($decryptorName): string
	{
		$rc = range(chr(128), chr(255));
		$var = [
			"string" => "\${$this->escape($this->gen(10, 3, $rc))}",
			"key" => "\${$this->escape($this->gen(10, 3, $rc))}",
			"binary" => "\${$this->escape($this->gen(10, 3, $rc))}",
			"slen" => "\${\"{$this->escape($this->gen(10, 3, $rc))}\"}",
			"salt" => "\${\"{$this->escape($this->gen(10, 3, $rc))}\"}",
			"klen" => "\${\"{$this->escape($this->gen(10, 3, $rc))}\"}",
			"new" => "\${\"{$this->escape($this->gen(10, 3, $rc))}\"}",
			"r" => "\${\"{$this->escape($this->gen(10, 3, $rc))}\"}",
			"cost" => "\${\"{$this->escape($this->gen(10, 3, $rc))}\"}",
			"i" => "\${\"{$this->escape($this->gen(10, 3, $rc))}\"}",
			"j" => "\${\"{$this->escape($this->gen(10, 3, $rc))}\"}",
			"k" => "\${\"{$this->escape($this->gen(10, 3, $rc))}\"}"
		];
		
		return 'function '.$decryptorName.'('.$var["string"].', '.$var["key"].', '.$var["binary"].' = false) { if ('.$var["binary"].') { '.$var["string"].' = base64_decode(strrev('.$var["string"].')); } '.$var["slen"].' = strlen('.$var["string"].'); '.$var["salt"].' = substr('.$var["string"].', '.$var["slen"].' - 5); '.$var["string"].' = substr('.$var["string"].', 0, ('.$var["slen"].' = '.$var["slen"].' - 5)); '.$var["klen"].' = strlen('.$var["key"].'); '.$var["new"].' = '.$var["r"].' = ""; '.$var["cost"].' = 1; for('.$var["i"].'='.$var["j"].'=0;'.$var["i"].'<'.$var["klen"].';'.$var["i"].'++) { '.$var["new"].' .= chr(ord('.$var["key"].'['.$var["i"].']) ^ ord('.$var["salt"].'['.$var["j"].'++])); if ('.$var["j"].' === 5) { '.$var["j"].' = 0; } } '.$var["new"].' = sha1('.$var["new"].'); for('.$var["i"].'='.$var["j"].'='.$var["k"].'=0;'.$var["i"].'<'.$var["slen"].';'.$var["i"].'++) { '.$var["r"].' .= chr( ord('.$var["string"].'['.$var["i"].']) ^ ord('.$var["new"].'['.$var["j"].'++]) ^ ord('.$var["salt"].'['.$var["k"].'++]) ^ ('.$var["i"].' << '.$var["j"].') ^ ('.$var["k"].' >> '.$var["j"].') ^ ('.$var["slen"].' % '.$var["cost"].') ^ ('.$var["cost"].' >> '.$var["j"].') ^ ('.$var["cost"].' >> '.$var["i"].') ^ ('.$var["cost"].' >> '.$var["k"].') ^ ('.$var["cost"].' ^ ('.$var["slen"].' % ('.$var["i"].' + '.$var["j"].' + '.$var["k"].' + 1))) ^ (('.$var["cost"].' << '.$var["i"].') % 2) ^ (('.$var["cost"].' << '.$var["j"].') % 2) ^ (('.$var["cost"].' << '.$var["k"].') % 2) ^ (('.$var["cost"].' * ('.$var["i"].'+'.$var["j"].'+'.$var["k"].')) % 3) ); '.$var["cost"].'++; if ('.$var["j"].' === '.$var["klen"].') { '.$var["j"].' = 0; } if ('.$var["k"].' === 5) { '.$var["k"].' = 0; } } return '.$var["r"].'; }';
	}

	/**
	 * @param string $str
	 * @return string
	 */
	private function chrToHex($str)
	{
		return "\\x".dechex(ord($str));
	}

	/**
	 * @param string $str
	 * @return string
	 */
	private function chrToOct($str)
	{
		return "\\".decoct(ord($str));
	}

	/**
	 * @param string $str
	 * @return string
	 */
	private function convert($str)
	{
		$r = "";
		foreach (str_split($str) as $char) {
			$r .= rand(2, 3) % 2 ? $this->chrToOct($char) : $this->chrToHex($char);
		}
		return $r;
	}

	private const SELF_SIGN = "~.rodata:adb";
	private const SUB_SIGN = "\$\$\$adb\$\$\$";
	private const CLOSE_SIGN = "#####";
}
